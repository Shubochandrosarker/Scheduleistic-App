<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="sc-btn sc-btn-danger">
        <slot />
    </button>
</template>
