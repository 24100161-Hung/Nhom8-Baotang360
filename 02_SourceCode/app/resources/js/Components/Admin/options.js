// Danh sách mã cố định dùng trong form quản trị (khớp data-model BaoTang360)
export const TAXA = ['dinosauria', 'mammalia', 'aves', 'reptilia', 'amphibia', 'pisces', 'insecta', 'arthropoda', 'mollusca', 'invertebrata', 'mineral', 'rock', 'meteorite', 'fossil_plant', 'plantae', 'hominini', 'crustacea', 'fossil_invertebrate', 'fossil_vertebrate', 'fungi', 'human', 'other'];
export const LICENSES = ['cc_by_sa_3', 'cc_by_4', 'cc0', 'other', 'simulated'];
export const AUDIO_LOCALES = ['vi', 'en', 'de', 'nl'];
export const HOTSPOT_TYPES = ['info', 'scene', 'artifact'];
export const DIFFICULTIES = ['de', 'vua', 'kho'];
export const TOUR_MODES = ['free', 'guided', 'both'];
export const PACKAGE_TYPES = ['single', 'membership'];
export const VISIT_STATUSES = ['open', 'sold_out', 'closed'];
export const ORDER_STATUSES = ['pending', 'paid', 'cancelled', 'expired', 'refunded'];
export const REVIEW_STATUSES = ['pending', 'approved', 'rejected'];
export const GUESTBOOK_STATUSES = ['pending', 'approved', 'hidden'];
export const CONTACT_STATUSES = ['new', 'read', 'replied'];

/** Mã chuỗi hoặc enum backend [{code, name}] → options [{value,label}] (nhãn: name, nếu không thì enums.<group>.<code>). */
export const codeOptions = (codes, te, group) =>
    (codes || []).map((c) => (c && typeof c === 'object' ? { value: c.code ?? c.value, label: c.name ?? c.label ?? te(group, c.code ?? c.value) } : { value: c, label: te(group, c) }));

/** Số hoặc chuỗi rỗng → null/number để gửi backend. */
export const numOrNull = (v) => (v === '' || v === null || v === undefined ? null : Number(v));

/** Làm tròn 2 chữ số thập phân (pitch/yaw). */
export const round2 = (v) => Math.round(Number(v) * 100) / 100;
