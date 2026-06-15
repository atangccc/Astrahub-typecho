type ToastKind = "success" | "error" | "warning" | "info";

const COLORS: Record<ToastKind, string> = {
  success: "#16a34a",
  error: "#dc2626",
  warning: "#f59e0b",
  info: "#2563eb",
};

let container: HTMLElement | null = null;

function ensureContainer(): HTMLElement {
  if (container && document.body.contains(container)) {
    return container;
  }
  container = document.createElement("div");
  container.className = "ah-toast-container";
  container.style.cssText =
    "position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none";
  document.body.appendChild(container);
  return container;
}

function show(kind: ToastKind, message: string) {
  const root = ensureContainer();
  const el = document.createElement("div");
  el.textContent = message;
  el.style.cssText = [
    "pointer-events:auto",
    "min-width:200px;max-width:360px",
    "padding:10px 14px",
    "border-radius:12px",
    "background:#fff",
    `border-left:4px solid ${COLORS[kind]}`,
    "box-shadow:0 8px 24px rgba(2,6,23,.12)",
    "color:#1e293b;font-size:13px;font-weight:600",
    "opacity:0;transform:translateY(-8px);transition:all .2s ease",
  ].join(";");
  root.appendChild(el);
  requestAnimationFrame(() => {
    el.style.opacity = "1";
    el.style.transform = "translateY(0)";
  });
  setTimeout(() => {
    el.style.opacity = "0";
    el.style.transform = "translateY(-8px)";
    setTimeout(() => el.remove(), 220);
  }, 3000);
}

export const Toast = {
  success: (m: string) => show("success", m),
  error: (m: string) => show("error", m),
  warning: (m: string) => show("warning", m),
  info: (m: string) => show("info", m),
};
