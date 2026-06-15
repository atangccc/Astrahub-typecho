import { createApp } from "vue";
import App from "./App.vue";
import { configureApi } from "./api";

function mount() {
  const el = document.getElementById("astrahub-app");
  if (!el) {
    return;
  }
  const actionUrl = el.getAttribute("data-action-url") || "";
  configureApi(actionUrl);
  el.innerHTML = "";
  createApp(App).mount(el);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mount);
} else {
  mount();
}
