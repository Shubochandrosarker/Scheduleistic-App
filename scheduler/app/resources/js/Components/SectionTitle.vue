<template>
    <div class="md:col-span-1 flex justify-between">
        <div class="px-4 sm:px-0">
            <h3 class="text-[16px] font-bold text-t1">
                <slot name="title" />
            </h3>

            <p class="mt-1.5 text-[13px] leading-relaxed text-t3">
                <slot name="description" />
            </p>
        </div>

        <div class="px-4 sm:px-0">
            <slot name="aside" />
        </div>
    </div>
</template>
