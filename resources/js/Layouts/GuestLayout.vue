<script setup>
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center pt-4 pb-8 px-4 sm:justify-center sm:pt-0 sm:pb-0 bg-cover bg-center bg-no-repeat bg-fixed relative min-h-[100dvh]"
        style="background-image: url('/bossing.png');"
    >
        <!-- Overlay for better text readability -->
        <div class="absolute inset-0 bg-white/20"></div>

        <div class="flex flex-col items-center mb-4 sm:mb-6 relative z-10 background-transparent">
            <h1 class="text-lg sm:text-2xl text-white font-bold text-gray-800 text-center drop-shadow-lg px-2">
                Welcome to Bossing Loan Monitoring App
            </h1>
        </div>

        <div
            class="mb-8 sm:mb-[200px] w-full max-w-full overflow-hidden px-4 py-4 sm:px-6 shadow-md sm:max-w-md sm:rounded-lg relative z-10 bg-white/90 backdrop-blur-sm"
        >
            <slot />
        </div>
    </div>
</template>
