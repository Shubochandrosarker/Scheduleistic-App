<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div v-show="message">
        <p class="text-[12.5px] font-semibold text-bad">
            {{ message }}
        </p>
    </div>
</template>
