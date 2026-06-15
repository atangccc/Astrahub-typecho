import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "node:path";

// 独立库构建：把 Vue 应用打成单一 astrahub.js + astrahub.css，
// 输出到插件根目录的 assets/，由 panel.php 直接 <script>/<link> 引入。
// 不依赖任何外部 UI 框架包，Vue 一并打包进产物，后台无需联网取 CDN。
export default defineConfig({
  plugins: [vue()],
  define: {
    // 生产环境关闭 Vue devtools/警告
    __VUE_OPTIONS_API__: "true",
    __VUE_PROD_DEVTOOLS__: "false",
    __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: "false",
    // 浏览器无 Node 的 process 全局：静态替换 NODE_ENV，
    // 避免 3d-force-graph/three 依赖在运行时读 process.env 抛 ReferenceError。
    "process.env.NODE_ENV": JSON.stringify("production"),
    "process.env": "{}",
  },
  build: {
    outDir: resolve(__dirname, "../assets"),
    emptyOutDir: false,
    cssCodeSplit: false,
    lib: {
      entry: resolve(__dirname, "src/main.ts"),
      name: "AstraHubConsole",
      formats: ["iife"],
      fileName: () => "astrahub.js",
    },
    rollupOptions: {
      output: {
        // 兜底 process shim：放在产物最前面，覆盖 define 替换不到的裸 process 引用
        // （部分 three / 3d-force-graph 依赖会探测 process.platform 等）。
        banner: "if(typeof globalThis!=='undefined'&&typeof globalThis.process==='undefined'){globalThis.process={env:{NODE_ENV:'production'},platform:'',argv:[],version:'',versions:{},nextTick:function(f){Promise.resolve().then(f);}};}",
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith(".css")) {
            return "astrahub.css";
          }
          return "[name][extname]";
        },
      },
    },
  },
});
