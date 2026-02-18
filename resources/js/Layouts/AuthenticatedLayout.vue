<script setup>
import { ref, computed, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { UserOutlined, DollarCircleOutlined, AuditOutlined, DashboardOutlined, HomeOutlined, AccountBookOutlined } from '@ant-design/icons-vue';
import Chatbot from '@/Components/Chatbot.vue';

const showingNavigationDropdown = ref(false);
const openKeys = ref([]);

const closeMobileMenu = () => {
    showingNavigationDropdown.value = false;
};

const page = usePage();
watch(() => page.url, () => {
    showingNavigationDropdown.value = false;
});

// Determine selected key based on current route
const selectedKeys2 = computed(() => {
    const routeName = page.url.split('?')[0];
    
    if (routeName === '/dashboard' || routeName === '/') {
        return ['0'];
    } else if (routeName.startsWith('/members')) {
        return ['1'];
    } else if (routeName.startsWith('/monthly-contributions')) {
        return ['2'];
    } else if (routeName.startsWith('/loans')) {
        return ['3'];
    } else if (routeName.startsWith('/capital-cash-flow')) {
        return ['4'];
    }
    return ['0'];
});

// Dynamic breadcrumb items based on current route
const breadcrumbItems = computed(() => {
    const routeName = page.url.split('?')[0];
    const items = [
        {
            title: 'Home',
            href: route('dashboard'),
        }
    ];

    if (routeName === '/dashboard' || routeName === '/') {
        items.push({
            title: 'Dashboard',
        });
    } else if (routeName.startsWith('/members')) {
        items.push({
            title: 'Members',
        });
    } else if (routeName.startsWith('/loans')) {
        items.push({
            title: 'Loans',
        });
    } else if (routeName.startsWith('/profile')) {
        items.push({
            title: 'Profile',
        });
    } else if (routeName.startsWith('/monthly-contributions')) {
        items.push({
            title: 'Monthly Contributions',
        });
    } else if (routeName.startsWith('/capital-cash-flow')) {
        items.push({
            title: 'Capital and Cash Flow',
        });
    }

    return items;
});

const handleMenuClick = ({ key }) => {
    switch (key) {
        case '0':
            router.visit(route('dashboard'));
            break;
        case '1':
            router.visit(route('members.index'));
            break;
        case '2':
            router.visit(route('monthly-contributions.index'));
            break;
        case '3':
            router.visit(route('loans.index'));
            break;
        case '4':
            router.visit(route('capital-cash-flow.index'));
            break;
    }
};
</script>

<template>
    <div>
        <nav
                class="border-b border-blue-400 bg-blue-300"
            >
                <!-- Primary Navigation Menu -->
                <div class=" px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                           
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center truncate max-w-[160px] sm:max-w-[220px] lg:max-w-none text-sm sm:text-base font-semibold text-gray-800">
                                BOSSING COOPERATIVE SOCIETY LTD.
                            </div>

                            <!-- Navigation Links -->
                            <!-- <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                            </div> -->
                        </div>

                        <div class="hidden lg:ms-6 lg:flex lg:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger (visible when sidebar is hidden: below lg) -->
                        <div class="-me-2 flex items-center lg:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu (mobile: full nav + user) -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="lg:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('members.index')"
                            :active="route().current('members.*')"
                        >
                            Members
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('monthly-contributions.index')"
                            :active="route().current('monthly-contributions.*')"
                        >
                            Monthly Contributions
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('loans.index')"
                            :active="route().current('loans.*')"
                        >
                            Loans
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('capital-cash-flow.index')"
                            :active="route().current('capital-cash-flow.*')"
                        >
                            Capital and Cash Flow
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>
        <a-layout-content class="px-4 sm:px-6 lg:px-12 bg-white">
      <a-breadcrumb class="my-3 sm:my-4 text-xs sm:text-sm flex flex-wrap">
        <a-breadcrumb-item v-for="(item, index) in breadcrumbItems" :key="index">
          <Link v-if="item.href" :href="item.href" style="color: inherit;">
            <HomeOutlined v-if="index === 0" style="margin-right: 4px;" />
            {{ item.title }}
          </Link>
          <span v-else>{{ item.title }}</span>
        </a-breadcrumb-item>
      </a-breadcrumb>
      <a-layout class="pt-4 sm:pt-6 pb-6 bg-white">
        <div class="hidden lg:block shrink-0" style="width: 250px;">
        <a-layout-sider :width="250" style="background: #fff; position: static;">
          <a-menu
            :selectedKeys="selectedKeys2"
            v-model:openKeys="openKeys"
            mode="inline"
            style="height: 100%;"
            @click="handleMenuClick"
          >
            <a-menu-item key="0">
                <span class="flex items-center gap-2">
                    <div class="mb-1">
                        <DashboardOutlined/>
                    </div>
                    <div>
                        Dashboard
                    </div>
                </span>
            </a-menu-item>
            <a-menu-item key="1">
                <span class="flex items-center gap-2">
                    <div class="mb-1">
                  <UserOutlined />
                  </div>
                  <div>
                  Members
                  </div>
                </span>
            </a-menu-item>
            <a-menu-item key="2">
                <span class="flex items-center gap-2">
                    <div class="mb-1">
                    <AuditOutlined />
                    </div>
                    <div>
                  Monthly Contributions
                  </div>
                </span>
            </a-menu-item>
            <a-menu-item key="3">
                <span class="flex items-center gap-2">
                    <div class="mb-1">
                    <DollarCircleOutlined />
                    </div>
                    <div>
                  Loans
                  </div>
                </span>
            </a-menu-item>
            <a-menu-item key="4">
                <span class="flex items-center gap-2">
                    <div class="mb-1">
                    <AccountBookOutlined />
                    </div>
                    <div>
                  Capital and Cash Flow
                  </div>
                </span>
            </a-menu-item>
          </a-menu>
        </a-layout-sider>
        </div>
        
            <!-- Page Content -->
            <main class="min-h-screen w-full">
                <header
                class=" mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
                v-if="$slots.header"
            >
                <div >
                    <slot name="header" />
                </div>
            </header>
                <slot />
            </main>
      </a-layout>
    </a-layout-content>
    <a-layout-footer class="text-center text-xs sm:text-sm py-4 hidden sm:block">
      Bossing Loan Monitoring
    </a-layout-footer>
    <Chatbot />
    </div>
</template>
