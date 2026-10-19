<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Audio guide đa ngôn ngữ (vi/en/de/nl theo các bản có sẵn): mode "file" phát <audio> MP3,
 * mode "tts" đọc transcript bằng speechSynthesis. Hiển thị transcript, tự dừng khi đổi `resetKey`
 * (đổi scene). Phát sự kiện `play` để ghi visit_events audio_play.
 */
const props = defineProps({
    audios: { type: Array, default: () => [] },
    resetKey: { type: [String, Number], default: null },
    dark: { type: Boolean, default: false },
    title: { type: String, default: '' },
});
const emit = defineEmits(['play']);
const { t, te, locale: pageLocale } = useT();
const { clock } = useFormat();

const BCP47 = { vi: 'vi-VN', en: 'en-US', de: 'de-DE', nl: 'nl-NL' };
const ORDER = ['vi', 'en', 'de', 'nl'];

const available = computed(() =>
    [...new Set(props.audios.map((a) => a.locale).filter(Boolean))].sort((a, b) => ORDER.indexOf(a) - ORDER.indexOf(b)),
);
const lang = ref(null);
const audio = computed(() => props.audios.find((a) => a.locale === lang.value) || props.audios[0] || null);
const mode = computed(() => (audio.value?.url && audio.value?.mode !== 'tts' ? 'file' : 'tts'));

const player = ref(null);
const supported = ref(true);
const speaking = ref(false);
const paused = ref(false);
const hasVoice = ref(true);
const showTranscript = ref(false);
let voices = [];

function pickDefault() {
    const list = available.value;
    lang.value = list.includes(pageLocale.value) ? pageLocale.value : list[0] || null;
}

function loadVoices() {
    voices = window.speechSynthesis?.getVoices?.() || [];
}

function report() {
    if (!audio.value) return;
    emit('play', { audio_id: audio.value.id, locale: audio.value.locale, mode: mode.value });
}

function speak() {
    const synth = window.speechSynthesis;
    if (!synth || !audio.value?.transcript) return;
    if (paused.value) {
        synth.resume();
        paused.value = false;
        return;
    }
    synth.cancel();
    const u = new SpeechSynthesisUtterance(audio.value.transcript);
    u.lang = BCP47[audio.value.locale] || 'vi-VN';
    const prefix = (audio.value.locale || 'vi').toLowerCase();
    const voice = voices.find((v) => v.lang?.toLowerCase().startsWith(prefix)) || null;
    hasVoice.value = !!voice;
    if (voice) u.voice = voice;
    u.rate = 0.95;
    u.onend = u.onerror = () => {
        speaking.value = false;
        paused.value = false;
    };
    synth.speak(u);
    speaking.value = true;
    report();
}
function pauseSpeech() {
    window.speechSynthesis?.pause();
    paused.value = true;
}

/** Dừng mọi âm thanh (đổi scene, đổi ngôn ngữ, rời trang). */
function stopAll() {
    if (player.value) {
        player.value.pause();
        player.value.currentTime = 0;
    }
    if (supported.value) window.speechSynthesis?.cancel();
    speaking.value = false;
    paused.value = false;
}

watch(() => props.resetKey, () => {
    stopAll();
    pickDefault();
});
watch(lang, stopAll);
watch(available, (list) => {
    if (!list.includes(lang.value)) pickDefault();
});

onMounted(() => {
    pickDefault();
    supported.value = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    if (supported.value) {
        loadVoices();
        window.speechSynthesis.addEventListener?.('voiceschanged', loadVoices);
    }
});
onBeforeUnmount(() => {
    stopAll();
    if (supported.value) window.speechSynthesis.removeEventListener?.('voiceschanged', loadVoices);
});
defineExpose({ stop: stopAll });
</script>

<template>
    <section v-if="audio" class="audio-guide rounded-xl p-3" :class="dark ? 'is-dark' : 'card p-4'" :aria-label="title || t('audio.title')">
        <div class="flex flex-wrap items-center gap-2">
            <span class="audio-guide__icon"><Icon name="volume" :size="18" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="font-body text-sm font-bold">{{ title || t('audio.title') }}</h2>
                <p class="audio-guide__muted text-xs">
                    {{ mode === 'file' ? t('audio.mode_file') : t('audio.mode_tts') }}<span v-if="audio.duration_sec"> · {{ clock(audio.duration_sec) }}</span>
                </p>
            </div>
            <div v-if="available.length > 1" class="flex gap-1" role="radiogroup" :aria-label="t('audio.language')">
                <button
                    v-for="code in available"
                    :key="code"
                    type="button"
                    role="radio"
                    class="audio-guide__lang"
                    :aria-checked="lang === code"
                    :title="te('locale', code)"
                    @click="lang = code"
                >
                    {{ code.toUpperCase() }}
                </button>
            </div>
        </div>

        <div class="mt-2">
            <audio
                v-if="mode === 'file'"
                :key="audio.id"
                ref="player"
                :src="audio.url"
                controls
                preload="none"
                class="w-full"
                :lang="audio.locale"
                :aria-label="`${title || t('audio.title')} (${te('locale', audio.locale)})`"
                @play="report"
            >
                {{ t('audio.not_supported') }}
            </audio>
            <template v-else>
                <p v-if="!supported" class="rounded-lg bg-warning-soft p-2 text-xs text-warning" role="alert">{{ t('audio.tts_not_supported') }}</p>
                <div v-else class="flex flex-wrap items-center gap-2">
                    <button v-if="!speaking || paused" type="button" class="btn btn-sm" :class="dark ? 'btn-accent' : 'btn-primary'" :disabled="!audio.transcript" @click="speak">
                        <Icon name="play" :size="16" />{{ paused ? t('audio.resume') : t('audio.play') }}
                    </button>
                    <button v-else type="button" class="btn btn-sm" @click="pauseSpeech"><Icon name="pause" :size="16" />{{ t('audio.pause') }}</button>
                    <button type="button" class="btn btn-sm" :disabled="!speaking" @click="stopAll"><Icon name="stop" :size="16" />{{ t('audio.stop') }}</button>
                    <span v-if="speaking && !paused" class="audio-guide__muted text-xs" aria-live="polite">{{ t('audio.speaking') }}</span>
                </div>
                <p v-if="supported && !hasVoice" class="audio-guide__muted mt-1 text-xs">{{ t('audio.no_voice', { lang: audio.locale }) }}</p>
            </template>
        </div>

        <div v-if="audio.transcript" class="mt-2">
            <button type="button" class="text-xs font-semibold underline underline-offset-2" :aria-expanded="showTranscript" @click="showTranscript = !showTranscript">
                {{ showTranscript ? t('audio.hide_transcript') : t('audio.show_transcript') }}
            </button>
            <p v-show="showTranscript" class="audio-guide__transcript mt-2 max-h-40 overflow-y-auto whitespace-pre-line rounded-lg p-2 text-xs leading-relaxed" :lang="audio.locale">{{ audio.transcript }}</p>
        </div>
        <p v-if="audio.license || audio.source_name" class="audio-guide__muted mt-2 text-[11px]">
            {{ t('provenance.source') }}: {{ audio.source_name || '—' }}<span v-if="audio.license"> · {{ te('license', audio.license) }}</span>
        </p>
    </section>
</template>

<style scoped>
.audio-guide.is-dark {
    background: var(--ds-viewer-panel);
    color: var(--ds-viewer-text);
    border: 1px solid var(--ds-viewer-line);
    backdrop-filter: blur(10px);
}
.audio-guide.is-dark h2 { color: inherit; }
.audio-guide__icon {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--ds-accent-soft);
    color: var(--ds-accent-strong);
}
.is-dark .audio-guide__icon { background: rgb(240 190 110 / 0.18); color: #f0be6e; }
.audio-guide__muted { color: var(--ds-muted); }
.is-dark .audio-guide__muted { color: var(--ds-viewer-muted); }
.audio-guide__lang {
    min-width: 2.25rem;
    min-height: 2rem;
    padding: 0 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--ds-border);
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
}
.is-dark .audio-guide__lang { border-color: var(--ds-viewer-line); }
.audio-guide__lang[aria-checked='true'] { background: var(--ds-primary); border-color: var(--ds-primary); color: var(--ds-on-primary); }
.is-dark .audio-guide__lang[aria-checked='true'] { background: #f0be6e; border-color: #f0be6e; color: #1a1206; }
.audio-guide__transcript { background: var(--ds-surface-2); }
.is-dark .audio-guide__transcript { background: rgb(255 255 255 / 0.06); }
.is-dark audio { color-scheme: dark; }
</style>
