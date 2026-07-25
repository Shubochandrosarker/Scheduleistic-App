<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="sc-btn sc-btn-primary">
        <slot />
    </button>
</template>
