<script setup lang="ts">
/**
 * Replaces <input type="date"> and <input type="datetime-local">.
 *
 * The native control's calendar is an OS popup: it can't be themed, it always
 * shows English month names in mm/dd/yyyy order, and it ignores the page's
 * direction — so on the Arabic dashboard it opened LTR with a stray "Clear"
 * button. This renders the calendar itself, localized and direction-aware.
 *
 * Emits the same strings the form already sends: "YYYY-MM-DD", or
 * "YYYY-MM-DDTHH:mm" when `withTime` is set. Empty string means unset.
 */
import { CalendarDate, type DateValue, getLocalTimeZone, today } from '@internationalized/date';
import { CalendarIcon, ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNext,
    CalendarPrev,
    CalendarRoot,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'radix-vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        withTime?: boolean;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    { withTime: false, disabled: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const { t, locale } = useI18n();

const open = ref(false);

/** "2026-08-13T14:30" -> ["2026-08-13", "14:30"] */
const datePart = computed(() => (props.modelValue ?? '').split('T')[0] ?? '');
const timePart = computed(() => (props.modelValue ?? '').split('T')[1] ?? '');

const calendarValue = computed<DateValue | undefined>(() => {
    const [year, month, day] = datePart.value.split('-').map(Number);

    return year && month && day ? new CalendarDate(year, month, day) : undefined;
});

function emitParts(date: string, time: string): void {
    if (date === '') {
        emit('update:modelValue', '');

        return;
    }

    emit('update:modelValue', props.withTime ? `${date}T${time === '' ? '00:00' : time}` : date);
}

function onDateSelected(value: DateValue | undefined): void {
    if (value === undefined) {
        emitParts('', '');

        return;
    }

    const iso = `${value.year}-${String(value.month).padStart(2, '0')}-${String(value.day).padStart(2, '0')}`;
    emitParts(iso, timePart.value);

    if (!props.withTime) {
        open.value = false;
    }
}

function onTimeInput(event: Event): void {
    const time = (event.target as HTMLInputElement).value;
    emitParts(datePart.value === '' ? today(getLocalTimeZone()).toString() : datePart.value, time);
}

function clear(): void {
    emitParts('', '');
    open.value = false;
}

/** What the trigger shows — localized, never mm/dd/yyyy. */
const displayValue = computed(() => {
    if (datePart.value === '') return '';

    const [year, month, day] = datePart.value.split('-').map(Number);
    const formatted = new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : 'en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(year, month - 1, day));

    return props.withTime && timePart.value !== '' ? `${formatted} · ${timePart.value}` : formatted;
});
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            :disabled="disabled"
            class="flex w-full items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-start text-sm text-gray-900 shadow-sm transition-colors hover:border-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:cursor-not-allowed disabled:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:shadow-none dark:hover:border-gray-600 dark:focus:ring-blue-500/40 dark:disabled:bg-gray-900/50"
        >
            <CalendarIcon class="h-4 w-4 shrink-0 text-gray-400" />
            <span v-if="displayValue" class="truncate">{{ displayValue }}</span>
            <span v-else class="truncate text-gray-400 dark:text-gray-500">{{ placeholder ?? t('admin.common.pick_date') }}</span>
            <X
                v-if="displayValue && !disabled"
                class="ms-auto h-3.5 w-3.5 shrink-0 text-gray-400 hover:text-red-500"
                role="button"
                :aria-label="t('admin.common.clear')"
                @click.stop.prevent="clear"
            />
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                :side-offset="4"
                class="z-50 rounded-lg border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900"
            >
                <CalendarRoot
                    v-slot="{ grid, weekDays }"
                    :model-value="calendarValue"
                    :locale="locale === 'ar' ? 'ar' : 'en-GB'"
                    :dir="locale === 'ar' ? 'rtl' : 'ltr'"
                    fixed-weeks
                    @update:model-value="onDateSelected"
                >
                    <CalendarHeader class="mb-2 flex items-center justify-between gap-2">
                        <CalendarPrev
                            class="rounded p-1 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                        >
                            <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                        </CalendarPrev>
                        <CalendarHeading class="text-sm font-semibold text-gray-900 dark:text-gray-100" />
                        <CalendarNext
                            class="rounded p-1 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                        >
                            <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                        </CalendarNext>
                    </CalendarHeader>

                    <CalendarGrid v-for="month in grid" :key="month.value.toString()" class="w-full border-collapse">
                        <CalendarGridHead>
                            <CalendarGridRow class="flex">
                                <CalendarHeadCell v-for="day in weekDays" :key="day" class="w-8 text-center text-[0.7rem] font-medium text-gray-400">
                                    {{ day }}
                                </CalendarHeadCell>
                            </CalendarGridRow>
                        </CalendarGridHead>
                        <CalendarGridBody>
                            <CalendarGridRow v-for="(week, index) in month.rows" :key="`week-${index}`" class="flex w-full">
                                <CalendarCell v-for="weekDate in week" :key="weekDate.toString()" :date="weekDate" class="p-0">
                                    <CalendarCellTrigger
                                        :day="weekDate"
                                        :month="month.value"
                                        class="flex h-8 w-8 items-center justify-center rounded text-sm text-gray-700 transition-colors hover:bg-gray-100 data-[selected]:bg-blue-600 data-[today]:font-semibold data-[outside-view]:text-gray-300 data-[selected]:text-white data-[today]:text-blue-600 data-[disabled]:opacity-40 data-[selected]:hover:bg-blue-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:data-[outside-view]:text-gray-600 dark:data-[selected]:text-white dark:data-[today]:text-blue-400"
                                    />
                                </CalendarCell>
                            </CalendarGridRow>
                        </CalendarGridBody>
                    </CalendarGrid>

                    <div v-if="withTime" class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <label class="text-xs text-gray-500 dark:text-gray-400">{{ t('admin.common.time') }}</label>
                        <input
                            type="time"
                            :value="timePart"
                            dir="ltr"
                            class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            @input="onTimeInput"
                        />
                        <button
                            type="button"
                            class="ms-auto rounded px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950"
                            @click="open = false"
                        >
                            {{ t('admin.common.done') }}
                        </button>
                    </div>
                </CalendarRoot>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
