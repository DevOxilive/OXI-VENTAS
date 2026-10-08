<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";

defineOptions({
  layout: AdminLayout,
});

const installerUrl = route("printers.qz-setup.download");
</script>

<template>
  <PageLayout>
    <section class="mx-auto w-full max-w-4xl space-y-5">
      <header class="rounded-3xl border border-secondary bg-background p-6 shadow-sm">
        <div class="flex items-start gap-4">
          <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-2xl text-white">
            <span class="material-symbols-outlined">print_connect</span>
          </div>
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Impresoras</p>
            <h1 class="mt-1 text-2xl font-black text-text">Configurar QZ Tray</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-text opacity-75">
              Descarga un paquete para preparar esta computadora sin exponer la llave privada de Super-Kay.
            </p>
          </div>
        </div>
      </header>

      <article class="rounded-3xl border border-secondary bg-background p-6 shadow-sm">
        <h2 class="text-lg font-black text-text">Instalador para esta caja</h2>
        <p class="mt-2 text-sm leading-6 text-text opacity-75">
          El ZIP contiene el certificado público, el instalador y un archivo <strong>INSTALAR-QZ.bat</strong> para ejecutarlo con doble clic.
          QZ Tray debe estar instalado antes de iniciar.
        </p>

        <a
          :href="installerUrl"
          class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-black text-white transition hover:brightness-95"
        >
          <span class="material-symbols-outlined text-lg">download</span>
          Descargar instalador de QZ Tray
        </a>
      </article>

      <article class="rounded-3xl border border-secondary bg-secondary p-6">
        <h2 class="text-base font-black text-text">Qué hace el instalador</h2>
        <ol class="mt-3 space-y-3 text-sm leading-6 text-text opacity-80">
          <li><strong>1.</strong> Descomprime el ZIP completo.</li>
          <li><strong>2.</strong> Abre <strong>INSTALAR-QZ.bat</strong> con el mismo usuario de Windows que usa Super-Kay.</li>
          <li><strong>3.</strong> El instalador autoriza el certificado público y reinicia QZ Tray.</li>
          <li><strong>4.</strong> Regresa al punto de venta e imprime un ticket de prueba.</li>
        </ol>
        <p class="mt-4 rounded-2xl border border-primary/20 bg-background p-3 text-xs leading-5 text-text opacity-75">
          La llave privada nunca se descarga: permanece en el servidor y solo firma las solicitudes de impresión.
        </p>
      </article>
    </section>
  </PageLayout>
</template>
