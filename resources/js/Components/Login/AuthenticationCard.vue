<template>
    <div class="relative min-h-screen overflow-hidden bg-[linear-gradient(135deg,#fff8ef_0%,#fffdf9_45%,#f7f4ee_100%)]">
        <div class="absolute inset-0">
            <div class="absolute -left-16 top-10 h-56 w-56 rounded-full bg-[#f59e0b]/12 blur-3xl" />
            <div class="absolute right-0 top-0 h-72 w-72 rounded-full bg-[#7c2d12]/10 blur-3xl" />
            <div class="absolute bottom-0 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-[#facc15]/10 blur-3xl" />
        </div>

        <div class="relative flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex justify-center">
                    <slot name="logo" />
                </div>

                <div class="rounded-[2rem] border border-amber-100/80 bg-white/95 p-5 shadow-2xl shadow-amber-950/10 ring-1 ring-white sm:p-8">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
