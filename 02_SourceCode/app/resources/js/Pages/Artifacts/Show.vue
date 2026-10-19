<script setup>
import { computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import MediaGallery from '@/Components/MediaGallery.vue';
import CoverImage from '@/Components/CoverImage.vue';
import FavoriteButton from '@/Components/FavoriteButton.vue';
import StarRating from '@/Components/StarRating.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import ReviewSection from '@/Components/ReviewSection.vue';
import AudioGuide from '@/Components/AudioGuide.vue';
import InfoList from '@/Components/Public/InfoList.vue';
import InterpretationBlock from '@/Components/Public/InterpretationBlock.vue';
import JsonLd from '@/Components/Public/JsonLd.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitTracker } from '@/Composables/useVisitTracker';

const props = defineProps({
    artifact: { type: Object, required: true },
    depth: { type: Number, default: 1 },
});
const { t, te } = useT();
const { number, country } = useFormat();
const lr = useLocaleRoute();
const tracker = useVisitTracker();

const a = computed(() => props.artifact || {});
const images = computed(() => a.value.media?.images || (a.value.cover ? [a.value.cover] : []));
const videos = computed(() => a.value.media?.videos || []);
// Audio của hiện vật (MediaItem) → dạng AudioItem cho AudioGuide
const audios = computed(() =>
    (a.value.media?.audios || []).map((m) => ({ id: m.id, locale: m.locale || 'vi', url: m.url, mode: m.url ? 'file' : 'tts', transcript: m.transcript || m.caption, duration_sec: m.duration_sec, license: m.license_label || m.license, source_name: m.source_name })),
);
const dims = computed(() => {
    const parts = [a.value.length_cm, a.value.width_cm, a.value.height_cm].filter((v) => v !== null && v !== undefined && v !== '');
    return parts.length ? `${parts.map((v) => number(v, 1)).join(' × ')} cm` : null;
});
const info = computed(() => [
    { label: t('artifacts.scientific_name'), value: a.value.scientific_name },
    { label: t('artifacts.catalog_number'), value: a.value.catalog_number },
    { label: t('artifacts.taxon'), value: a.value.taxon_group ? te('taxon', a.value.taxon_group) : null },
    { label: t('artifacts.period'), value: a.value.period?.name },
    { label: t('artifacts.origin'), value: [a.value.origin_locality, country(a.value.origin_country)].filter(Boolean).join(', ') },
    { label: t('artifacts.material'), value: a.value.material },
    { label: t('artifacts.dimensions'), value: dims.value },
    { label: t('artifacts.weight'), value: a.value.weight_kg ? `${number(a.value.weight_kg, 2)} kg` : null },
    { label: t('artifacts.year_collected'), value: a.value.year_collected },
    { label: t('artifacts.collector'), value: a.value.collector },
    { label: t('artifacts.source'), value: a.value.source_url ? t('artifacts.source_link') : null, href: a.value.source_url },
]);
const hotspotsIn = computed(() =>
    (a.value.hotspots_in || []).map((h) => {
        const slug = h.scene?.slug ?? h.slug;
        const q = new URLSearchParams({ pitch: String(h.pitch ?? 0), yaw: String(h.yaw ?? 0) });
        return { key: `${slug}-${h.yaw}`, title: h.scene?.title ?? h.title, url: lr(`/tham-quan-360/${slug}?${q}`) };
    }),
);

onMounted(() => tracker.track('artifact_view', { artifact_id: a.value.id, meta: { source: 'page' } }));
</script>

<template>
    <PageMeta :title="a.title" :description="a.summary" :image="a.cover?.url" />
    <JsonLd :data="a.jsonld" />
    <div class="container-page py-8">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('artifacts.title'), href: lr('/hien-vat') }, { label: a.title }]" class="mb-4" />

        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <section :aria-label="t('media.gallery')">
                <CoverImage :src="images[0]?.url" :alt="images[0]?.caption || a.title" icon="bone" ratio="aspect-[4/3]" eager class="rounded-xl" />
                <p v-if="a.image_author || a.image_license" class="mt-2 text-xs text-muted">
                    {{ t('artifacts.image_credit') }}: {{ a.image_author || '—' }}<span v-if="a.image_license"> · {{ a.image_license }}</span>
                </p>
                <div v-if="images.length > 1" class="mt-4">
                    <MediaGallery :images="images" :title="a.title" />
                </div>
            </section>

            <div class="min-w-0">
                <div class="flex flex-wrap gap-2">
                    <span v-if="a.is_highlight" class="badge badge-accent">{{ t('artifacts.highlight') }}</span>
                    <span v-if="a.taxon_group" class="badge">{{ te('taxon', a.taxon_group) }}</span>
                    <span v-if="a.period" class="badge badge-info">{{ a.period.name }}</span>
                </div>
                <h1 class="mt-2 text-3xl font-bold md:text-4xl">{{ a.title }}</h1>
                <p v-if="a.scientific_name" class="mt-1 text-lg italic text-muted">{{ a.scientific_name }}</p>
                <p v-if="a.summary" class="mt-4 text-lg leading-relaxed">{{ a.summary }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-muted">
                    <span v-if="a.rating_count" class="inline-flex items-center gap-1"><StarRating :value="a.rating_avg" readonly :size="16" />{{ t('reviews.count', { n: a.rating_count }) }}</span>
                    <span class="inline-flex items-center gap-1"><Icon name="eye" :size="16" />{{ t('blog.views_n', { n: number(a.view_count ?? 0) }) }}</span>
                    <Link v-if="a.scene" :href="lr(`/tham-quan-360/${a.scene.slug}`)" class="inline-flex items-center gap-1 hover:text-primary"><Icon name="door" :size="16" />{{ a.scene.title }}</Link>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <FavoriteButton v-if="a.id" type="artifact" :id="a.id" :active="!!a.is_favorite" />
                    <Link v-for="h in hotspotsIn" :key="h.key" :href="h.url" class="btn btn-accent">
                        <Icon name="panorama" :size="18" />{{ t('artifacts.view_in_360', { room: h.title }) }}
                    </Link>
                </div>
                <InfoList class="mt-6" :title="t('artifacts.specs')" :items="info" />
            </div>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <InterpretationBlock :interpretation="a.interpretation" :depth="depth" :available-depths="a.available_depths || [1]" />
            <aside class="space-y-4">
                <AudioGuide v-if="audios.length" :audios="audios" :title="t('artifacts.audio_title')" />
                <section v-for="v in videos" :key="v.id" class="card overflow-hidden">
                    <video :src="v.url" controls preload="none" class="aspect-video w-full bg-black" :aria-label="v.caption || a.title"></video>
                    <p v-if="v.caption" class="p-3 text-sm">{{ v.caption }}</p>
                </section>
            </aside>
        </div>

        <ReviewSection
            v-if="a.id"
            class="mt-12"
            subject-type="artifact"
            :subject-id="a.id"
            :reviews="a.reviews || []"
            :my-review="a.my_review"
            :rating-avg="a.rating_avg"
            :rating-count="a.rating_count || 0"
        />

        <section v-if="a.related?.length" class="mt-12">
            <SectionHeading :title="t('artifacts.related')" :href="lr('/hien-vat')" :link-text="t('common.view_all')" />
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <ArtifactCard v-for="r in a.related" :key="r.slug" :artifact="r" />
            </div>
        </section>
    </div>
</template>
