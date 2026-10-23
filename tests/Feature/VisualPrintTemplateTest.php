<?php

namespace Tests\Feature;

use App\Http\Controllers\VisualPrintTemplateController;
use App\Models\TicketTemplate;
use App\Support\VisualPrintLayout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VisualPrintTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique();
            $table->boolean('is_active')->default(true); $table->json('settings');
            $table->timestamps(); $table->softDeletes();
        });
    }

    private function layout(): array
    {
        return ['version'=>2,'width'=>62,'height'=>40,'auto_height'=>false,'elements'=>[
            ['id'=>'price','type'=>'field','x'=>3,'y'=>4,'width'=>56,'height'=>10,'text'=>'',
            'binding'=>'price','font_size'=>14,'bold'=>true,'align'=>'center','fill'=>'#000000',
            'stroke'=>0,'visible'=>true,'locked'=>false],
        ]];
    }

    private function request(array $data, bool $authorized = true): Request
    {
        $request = Request::create('/', 'PUT', $data);
        $user = new class($authorized) {
            public function __construct(private bool $authorized) {}
            public function hasPermission($permission): bool { return $this->authorized; }
        };
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_draft_publication_and_rollback_preserve_legacy(): void
    {
        $legacy = TicketTemplate::productLabelTemplate();
        $original = $legacy->fresh()->getAttributes();
        $controller = app(VisualPrintTemplateController::class);
        $draft = $controller->save($this->request(['name'=>'Etiqueta nueva','layout'=>$this->layout(),'revision'=>'']), 'label')->getData(true);
        $this->assertNull(VisualPrintLayout::active('label'));
        $published = $controller->activate($this->request(['active'=>true,'revision'=>$draft['revision']]), 'label')->getData(true);
        $this->assertSame(62, VisualPrintLayout::active('label')['width']);
        $changed = $this->layout(); $changed['width'] = 80;
        $saved = $controller->save($this->request(['name'=>'Etiqueta nueva','layout'=>$changed,'revision'=>$published['revision']]), 'label')->getData(true);
        $this->assertSame(62, VisualPrintLayout::active('label')['width']);
        $controller->activate($this->request(['active'=>false,'revision'=>$saved['revision']]), 'label');
        $this->assertNull(VisualPrintLayout::active('label'));
        $this->assertSame($original, $legacy->fresh()->getAttributes());
        $this->assertNull(VisualPrintLayout::active('sales'));
    }

    public function test_stale_revision_cannot_overwrite_a_design(): void
    {
        $controller = app(VisualPrintTemplateController::class);
        $controller->save($this->request(['name'=>'Uno','layout'=>$this->layout(),'revision'=>'']), 'sales');
        $this->expectException(ValidationException::class);
        $controller->save($this->request(['name'=>'Dos','layout'=>$this->layout(),'revision'=>'']), 'sales');
    }

    public function test_elements_outside_paper_are_rejected(): void
    {
        $layout = $this->layout(); $layout['elements'][0]['x'] = 20;
        $this->expectException(ValidationException::class);
        VisualPrintLayout::validate($layout);
    }

    public function test_unknown_variables_are_rejected(): void
    {
        $layout = $this->layout(); $layout['elements'][0]['binding'] = 'password';
        $this->expectException(ValidationException::class);
        VisualPrintLayout::validate($layout);
    }

    public function test_paper_format_is_saved_and_validated_against_width(): void
    {
        $layout = $this->layout();
        $layout['paper_format'] = 'continuous-62';
        $validated = VisualPrintLayout::validate($layout);
        $this->assertSame('continuous-62', $validated['paper_format']);
        $layout['paper_format'] = 'receipt-80';
        $this->expectException(ValidationException::class);
        VisualPrintLayout::validate($layout);
    }

    public function test_write_requires_permission(): void
    {
        $this->expectException(HttpException::class);
        app(VisualPrintTemplateController::class)->save($this->request([], false), 'sales');
    }
}
