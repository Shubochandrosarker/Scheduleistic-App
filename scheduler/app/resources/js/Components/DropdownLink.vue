<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div>
        <button v-if="as == 'button'" type="submit" class="block w-full px-4 py-2 text-start text-[13px] leading-5 text-t2 transition duration-150 ease-in-out hover:bg-hover hover:text-t1 focus:bg-hover focus:outline-none">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="block px-4 py-2 text-[13px] leading-5 text-t2 transition duration-150 ease-in-out hover:bg-hover hover:text-t1 focus:bg-hover focus:outline-none">
            <slot />
        </a>

        <Link v-else :href="href" class="block px-4 py-2 text-[13px] leading-5 text-t2 transition duration-150 ease-in-out hover:bg-hover hover:text-t1 focus:bg-hover focus:outline-none">
            <slot />
        </Link>
    </div>
</template>
