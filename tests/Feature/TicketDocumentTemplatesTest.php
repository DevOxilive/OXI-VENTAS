<?php

namespace Tests\Feature;

use App\Http\Controllers\TicketTemplateController;
use App\Models\TicketTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TicketDocumentTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('settings');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_documents_store_independent_templates_and_reuse_them(): void
    {
        $sales = TicketTemplate::salesTemplate();
        $statement = TicketTemplate::employeeCreditStatementTemplate();
        $return = TicketTemplate::documentTemplate('sale-return');
        $payment = TicketTemplate::documentTemplate('credit-payment');
        $this->assertCount(4, array_unique([$sales->id, $statement->id, $return->id, $payment->id]));
        $settings = $return->settings;
        $settings['footer_text'] = 'DEVOLUCION PERSONALIZADA';
        $return->update(['settings' => $settings]);
        $this->assertSame('DEVOLUCION PERSONALIZADA', TicketTemplate::documentTemplate('sale-return')->settings['footer_text']);
        $this->assertNotSame('DEVOLUCION PERSONALIZADA', TicketTemplate::salesTemplate()->settings['footer_text']);
        $this->assertSame($payment->id, TicketTemplate::documentTemplate('credit-payment')->id);
        $this->assertSame('standard', $payment->settings['items_format']);
        $this->assertFalse($return->settings['open_cash_drawer']);
        $this->assertSame(4, TicketTemplate::count());
    }

    public function test_document_editor_saves_only_the_requested_template(): void
    {
        $return = TicketTemplate::documentTemplate('sale-return');
        $sales = TicketTemplate::salesTemplate();
        $settings = $return->settings;
        $settings['paper_width'] = 32;
        $request = Request::create('/printers/tickets/templates/sale-return/'.$return->id, 'PUT', [
            'name' => 'Mi devolución', 'is_active' => true, 'settings' => $settings,
            'record_version' => $return->updated_at->toJSON(),
        ]);
        $route = new Route('PUT', 'printers/tickets/templates/{kind}/{ticketTemplate}', []);
        $route->name('printers.document-tickets.update');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        app(TicketTemplateController::class)->updateDocument($request, 'sale-return', $return);
        $this->assertSame(32, $return->fresh()->settings['paper_width']);
        $this->assertSame(48, $sales->fresh()->settings['paper_width']);
    }

    public function test_wrong_document_cannot_update_a_sales_template(): void
    {
        $this->expectException(HttpException::class);
        app(TicketTemplateController::class)->updateDocument(Request::create('/'), 'sale-return', TicketTemplate::salesTemplate());
    }

    public function test_document_routes_keep_ticket_permissions(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertContains('permission:systems.tickets.view,systems.tickets.update', $routes->getByName('printers.document-tickets.index')->gatherMiddleware());
        $this->assertContains('permission:systems.tickets.update', $routes->getByName('printers.document-tickets.update')->gatherMiddleware());
    }
}
