<template>
    <div class="flex justify-between gap-4 md:col-span-1">
        <div class="px-1">
            <h3 class="text-lg font-semibold text-slate-800">
                <slot name="title" />
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                <slot name="description" />
            </p>
        </div>

        <div class="px-1">
            <slot name="aside" />
        </div>
    </div>
</template>
