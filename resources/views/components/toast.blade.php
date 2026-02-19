<div
    x-data="{
        show: false,
        message: '',
        type: 'success',
        timer: null,
        typeStyles: {
            success: { bg: 'bg-green-50', border: 'border-green-500', text: 'text-green-800' },
            error:   { bg: 'bg-red-50',   border: 'border-red-500',   text: 'text-red-800' },
            warning: { bg: 'bg-yellow-50', border: 'border-yellow-500', text: 'text-yellow-800' },
            info:    { bg: 'bg-blue-50',  border: 'border-blue-500',  text: 'text-blue-800' },
        },
        get styles() { return this.typeStyles[this.type] ?? this.typeStyles.success; },
        notify(event) {
            this.message = event.detail.message;
            this.type = event.detail.type ?? 'success';
            this.show = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.show = false; }, 4000);
        },
    }"
    x-show="show"
    x-transition:enter="transform ease-out duration-300 transition"
    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @toast.window="notify($event)"
    :class="[styles.bg, 'border-l-4', styles.border]"
    class="fixed top-4 right-4 z-50 max-w-sm w-full p-4 rounded-lg shadow-lg"
    style="display: none;"
    role="alert"
    aria-live="polite"
>
    <div class="flex">
        <div class="flex-shrink-0">
            <template x-if="type === 'success'">
                <svg class="h-5 w-5" :class="styles.text" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
            </template>
            <template x-if="type === 'error'">
                <svg class="h-5 w-5" :class="styles.text" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
            </template>
            <template x-if="type === 'warning'">
                <svg class="h-5 w-5" :class="styles.text" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </template>
            <template x-if="type === 'info'">
                <svg class="h-5 w-5" :class="styles.text" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                </svg>
            </template>
        </div>
        <div class="ml-3 flex-1">
            <p class="text-sm font-medium" :class="styles.text" x-text="message"></p>
        </div>
        <div class="ml-3 flex-shrink-0">
            <button
                type="button"
                @click="show = false; clearTimeout(timer);"
                class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2"
                :class="[styles.text, styles.border]"
            >
                <span class="sr-only">Dismiss</span>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>
    </div>
</div>
