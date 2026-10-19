// Bộ chuyển markdown tối giản cho bản xem trước trong admin: escape HTML TRƯỚC rồi mới thay cú pháp,
// nên nội dung người dùng không thể chèn thẻ/thuộc tính. Bản hiển thị công khai do backend lọc.

function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function inline(s) {
    return s
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*(?!\s)(.+?)\*/g, '$1<em>$2</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" rel="noopener noreferrer" target="_blank">$1</a>');
}

/** Chuyển một khối (đoạn giữa các dòng trống) thành HTML. */
function block(chunk) {
    const lines = chunk.split('\n');
    const heading = chunk.match(/^(#{1,4})\s+(.*)$/);
    if (heading && lines.length === 1) {
        const level = Math.min(heading[1].length + 1, 4);
        return `<h${level}>${inline(heading[2])}</h${level}>`;
    }
    if (lines.every((l) => /^\s*[-*]\s+/.test(l))) return `<ul>${lines.map((l) => `<li>${inline(l.replace(/^\s*[-*]\s+/, ''))}</li>`).join('')}</ul>`;
    if (lines.every((l) => /^\s*\d+\.\s+/.test(l))) return `<ol>${lines.map((l) => `<li>${inline(l.replace(/^\s*\d+\.\s+/, ''))}</li>`).join('')}</ol>`;
    if (lines.every((l) => /^&gt;\s?/.test(l))) return `<blockquote>${inline(lines.map((l) => l.replace(/^&gt;\s?/, '')).join('<br>'))}</blockquote>`;
    return `<p>${inline(lines.join('<br>'))}</p>`;
}

export function renderMarkdown(src) {
    return escapeHtml(String(src || '').replace(/\r\n/g, '\n'))
        .split(/\n{2,}/)
        .map((c) => c.trim())
        .filter(Boolean)
        .map(block)
        .join('\n');
}
