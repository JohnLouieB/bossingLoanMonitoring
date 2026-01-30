<script setup>
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0 bg-cover bg-center bg-no-repeat bg-fixed relative"
        style="background-image: url('/bossing.png');"
    >
        <!-- Overlay for better text readability -->
        <div class="absolute inset-0 bg-white/20"></div>

        <div class="flex flex-col items-center mb-6 relative z-10 background-transparent">
            <h1 class="text-2xl text-white font-bold text-gray-800 text-center drop-shadow-lg">
                Welcome to Bossing Loan Monitoring App
            </h1>
        </div>

        <div
            class="mb-[200px] w-full overflow-hidden px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg relative z-10 bg-white/90 backdrop-blur-sm"
        >
            <slot />
        </div>
    </div>
</template>
