export interface ProductCardData {
    id: number;
    name: string;
    slug: string;
    sku: string;
    brand?: { name: string; slug: string } | null;
    price_cents: number;
    price_formatted: string;
    sale_price_cents: number | null;
    sale_price_formatted: string | null;
    current_price_formatted: string;
    /** Variants cost different amounts: the price shown is the cheapest. */
    price_from?: boolean;
    on_sale: boolean;
    discount_percent: number | null;
    image: string | null;
    status: string;
    in_stock: boolean;
    has_variants: boolean;
    is_featured: boolean;
    rating_avg: number | null;
    rating_count: number;
    /** Whether the signed-in customer has already saved this product. */
    wishlisted: boolean;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Laravel API-resource collection (Home/Catalog/Wishlist). */
export interface ResourceCollection<T> {
    data: T[];
    meta: {
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
    };
    links: { prev: string | null; next: string | null };
}

/** Plain paginator serialized with ->through(). */
export interface Paginator<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
}

export interface CartItemData {
    id: number;
    product_id: number;
    variant_id: number | null;
    name: string;
    slug: string;
    sku: string;
    image: string | null;
    quantity: number;
    unit_price_cents: number;
    unit_price_formatted: string;
    line_subtotal_cents: number;
    line_subtotal_formatted: string;
    line_discount_cents: number;
    line_total_cents: number;
    line_total_formatted: string;
    purchasable: boolean;
}

export interface CartData {
    items: CartItemData[];
    item_count: number;
    discount_cents: number;
    discount_formatted: string;
    total_cents: number;
    total_formatted: string;
    coupon: { code: string; type: string } | null;
    coupon_error: string | null;
}

export interface AddressData {
    id: number;
    label: string | null;
    name: string;
    phone: string;
    country: string;
    city: string;
    area: string | null;
    notes: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
}

export interface CategoryData {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    parent_id: number | null;
}
