/**
 * Shapes shared by the page builder (pages/admin/pages/*). Section `data`
 * follows the table in docs/adr/0001-headless-storefront-split.md; the server
 * validates it per type, so the client keeps it loosely typed.
 */

export type SectionType =
    | 'hero'
    | 'promo_tiles'
    | 'category_rail'
    | 'trust_strip'
    | 'product_carousel'
    | 'category_spotlight'
    | 'brand_strip'
    | 'image_banner'
    | 'rich_text'
    | 'newsletter';

export type SectionData = Record<string, any>;

export interface Section {
    id: number;
    type: SectionType;
    is_active: boolean;
    data: SectionData;
}

export interface ProductSummary {
    id: number;
    name: string;
    name_ar: string | null;
    sku: string;
    price_formatted: string;
    image: string | null;
    /** False when the storefront would skip it (draft, hidden, inactive category). */
    visible: boolean;
}

export interface CategoryOption {
    id: number;
    parent_id: number | null;
    name: string;
    name_ar: string | null;
    is_active: boolean;
}

export interface BuilderLimits {
    slides: number;
    tiles: number;
    trust_items: number;
    carousel: number;
}

/** What a freshly added section of each type starts with. */
export function defaultData(type: SectionType): SectionData {
    switch (type) {
        case 'hero':
            return { slides: [] };
        case 'promo_tiles':
            return { tiles: [] };
        case 'trust_strip':
            return { items: [{ icon: 'truck', title: '', title_ar: '', text: '', text_ar: '' }] };
        case 'product_carousel':
            return { title: '', title_ar: '', icon: '', source: 'featured', category_id: null, product_ids: [], limit: 8 };
        case 'category_spotlight':
            return {};
        case 'image_banner':
            return { image_path: null, link: '', title: '', title_ar: '' };
        case 'rich_text':
            return { body: '', body_ar: '' };
        case 'newsletter':
            return { title: '', title_ar: '', text: '', text_ar: '' };
        default:
            return { title: '', title_ar: '' };
    }
}

/** Icons the storefront's trust strip and carousel headings know. */
export const SECTION_ICONS = ['truck', 'wallet', 'shield', 'headset', 'star', 'tag', 'sparkles', 'flame', 'gift', 'clock'];

export function readXsrfToken(): string {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}
