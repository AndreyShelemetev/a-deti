import sanitizeHtml from "sanitize-html";

const options: sanitizeHtml.IOptions = {
  allowedTags: ["p", "h2", "h3", "h4", "h5", "ul", "ol", "li", "strong", "em", "a", "br", "table", "thead", "tbody", "tr", "td", "th", "img", "blockquote", "hr", "sup", "sub", "figure", "figcaption", "iframe", "span", "div"],
  allowedAttributes: {
    a: ["href", "target", "rel", "title"],
    img: ["src", "alt", "width", "height", "loading"],
    td: ["colspan", "rowspan"],
    th: ["colspan", "rowspan"],
    iframe: ["src", "width", "height", "allow", "allowfullscreen", "title"],
    "*": ["class"],
  },
  allowedIframeHostnames: ["www.youtube.com", "www.youtube-nocookie.com", "rutube.ru", "vk.com", "yandex.ru"],
  allowedSchemes: ["http", "https", "mailto", "tel"],
  transformTags: {
    a: (tagName, attribs) => {
      const href = attribs.href ?? "";
      const external = /^https?:\/\//i.test(href);
      return { tagName, attribs: { ...attribs, ...(external ? { target: "_blank", rel: "noopener" } : {}) } };
    },
  },
};

/** Sanitises HTML coming from the admin editor before it is stored. */
export function sanitizeRichHtml(html: string) {
  return sanitizeHtml(html, options).trim();
}
