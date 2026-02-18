<script setup>
import { ref, nextTick, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, X, Send } from 'lucide-vue-next';

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? null);
const storageKey = computed(() =>
    userId.value != null ? `chatbot_user_name_${userId.value}` : 'chatbot_user_name_guest'
);

const open = ref(false);
const input = ref('');
const loading = ref(false);
const userName = ref('');

onMounted(() => {
    if (storageKey.value) {
        userName.value = localStorage.getItem(storageKey.value) || '';
    }
});

watch(storageKey, (key) => {
    if (key) {
        userName.value = localStorage.getItem(key) || '';
    }
});

const initialBotMessage = computed(() =>
    userName.value
        ? `Hi ${userName.value}! I'm the Bossing Loan Monitoring assistant. Ask me anything—e.g. "How much is the available money for 2025?" or "What is this app?"`
        : 'Hi! What\'s your name?'
);

const messages = ref([]);

function initMessages() {
    if (messages.value.length === 0) {
        messages.value = [{ role: 'bot', text: initialBotMessage.value }];
    }
}

function formatBotText(text) {
    if (!text) return '';
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
}

async function send() {
    const text = (input.value || '').trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', text });
    input.value = '';
    loading.value = true;

    const expectingName = !userName.value;
    const payload = {
        message: text,
        expecting_name: expectingName,
        ...(userName.value && { user_name: userName.value }),
    };

    try {
        const { data } = await window.axios.post(route('chatbot.reply'), payload);
        messages.value.push({ role: 'bot', text: data.reply });

        if (data.name && storageKey.value) {
            userName.value = data.name;
            localStorage.setItem(storageKey.value, data.name);
        }
    } catch (e) {
        messages.value.push({
            role: 'bot',
            text: 'Something went wrong. I\'ll send you the email of the admin which is "dianojames2000@gmail.com" so you can get more help.',
        });
    } finally {
        loading.value = false;
        await nextTick();
        scrollToBottom();
    }
}

const chatBody = ref(null);
function scrollToBottom() {
    const el = chatBody.value;
    if (el) el.scrollTop = el.scrollHeight;
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        initMessages();
        nextTick(() => scrollToBottom());
    }
}
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-2">
        <!-- Chat panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div
                v-show="open"
                class="flex flex-col w-[360px] sm:w-[400px] max-w-[calc(100vw-3rem)] h-[480px] rounded-2xl border border-slate-200 bg-white shadow-xl overflow-hidden"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <span class="font-semibold text-slate-800">Bossing Assistant</span>
                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition"
                        aria-label="Close chat"
                        @click="open = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div
                    ref="chatBody"
                    class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50"
                >
                    <div
                        v-for="(msg, i) in messages"
                        :key="i"
                        class="flex"
                        :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm"
                            :class="msg.role === 'user'
                                ? 'bg-blue-500 text-white rounded-br-md'
                                : 'bg-white border border-slate-200 text-slate-700 shadow-sm rounded-bl-md'"
                        >
                            <span v-if="msg.role === 'user'">{{ msg.text }}</span>
                            <span
                                v-else
                                class="whitespace-pre-wrap"
                                v-html="formatBotText(msg.text)"
                            />
                        </div>
                    </div>
                    <div v-if="loading" class="flex justify-start">
                        <div class="bg-white border border-slate-200 rounded-2xl rounded-bl-md px-4 py-2.5 text-sm text-slate-500">
                            Thinking...
                        </div>
                    </div>
                </div>
                <form
                    class="p-3 border-t border-slate-200 bg-white"
                    @submit.prevent="send"
                >
                    <div class="flex gap-2">
                        <input
                            v-model="input"
                            type="text"
                            placeholder="Ask about the app..."
                            class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            :disabled="loading"
                        />
                        <button
                            type="submit"
                            class="flex shrink-0 items-center justify-center rounded-xl bg-blue-500 px-4 py-2.5 text-white hover:bg-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition"
                            :disabled="loading || !input.trim()"
                            aria-label="Send"
                        >
                            <Send class="h-5 w-5" />
                        </button>
                    </div>
                </form>
            </div>
        </Transition>

        <!-- Toggle button -->
        <button
            type="button"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-500 text-white shadow-lg hover:bg-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
            :aria-label="open ? 'Close chat' : 'Open chat'"
            @click="toggle"
        >
            <MessageCircle v-if="!open" class="h-7 w-7" />
            <X v-else class="h-7 w-7" />
        </button>
    </div>
</template>
