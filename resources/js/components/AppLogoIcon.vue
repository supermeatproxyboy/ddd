<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            d="M12 5.5C9 3.5 5.5 3 2.5 4.5v15C6 18 9 18.5 12 20c3-1.5 6-2 9.5-.5v-15C18.5 3 15 3.5 12 5.5Z"
        />
        <path
            d="M12 5.5V20M6 8l2.5.5M6 12l2.5.5M15.5 8.5 18 8M15.5 12.5 18 12"
        />
    </svg>
</template>
