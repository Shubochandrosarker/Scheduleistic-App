<script setup>
import BrandMark from '@/Layouts/Partials/BrandMark.vue';
</script>

<template>
    <!-- Jetstream screens ask for the mark; give them the tenant's brand glyph. -->
    <BrandMark :size="32" />
</template>
