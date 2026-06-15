<?php
/**
 * AstraHub 后台面板宿主页：挂载前端控制台 SPA。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

include 'header.php';
include 'menu.php';

$options = \Widget\Options::alloc();
$pluginUrl = rtrim($options->pluginUrl, '/') . '/AstraHub';
$actionUrl = \Typecho\Common::url('/action/astrahub', $options->index);
?>
<main class="astrahub-root">
  <div id="astrahub-app"
       data-action-url="<?php echo htmlspecialchars($actionUrl); ?>"
       data-plugin-url="<?php echo htmlspecialchars($pluginUrl); ?>">
    <div style="padding:40px;text-align:center;color:#64748b">AstraHub 控制台加载中…</div>
  </div>
</main>
<style>
.astrahub-root {
  display: block;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  margin-left: 0 !important;
  margin-right: 0 !important;
}
.astrahub-root #astrahub-app { display: block; width: 100%; height: calc(100vh - 120px); }
.astrahub-root .ah-page {
  width: 100%;
  box-sizing: border-box;
  height: calc(100vh - 120px);
  min-height: 480px;
}
#nprogress { display: none !important; }
</style>
<script>
(function () {
  function killProgress() {
    try { if (window.NProgress && typeof window.NProgress.done === 'function') window.NProgress.done(); } catch (e) {}
    var np = document.getElementById('nprogress');
    if (np) { np.style.display = 'none'; }
    var m = document.querySelector('main.astrahub-root');
    if (m) { m.style.marginLeft = '0'; m.style.marginRight = '0'; }
  }
  if (document.readyState !== 'loading') killProgress();
  else document.addEventListener('DOMContentLoaded', killProgress);
  window.addEventListener('load', killProgress);
  try {
    var mo = new MutationObserver(killProgress);
    mo.observe(document.documentElement, { childList: true, subtree: true });
    setTimeout(function () { mo.disconnect(); }, 8000);
  } catch (e) {}
  var n = 0, t = setInterval(function () { killProgress(); if (++n > 16) clearInterval(t); }, 500);
})();
</script>
<?php
$cssPath = __DIR__ . '/assets/astrahub.css';
$jsPath = __DIR__ . '/assets/astrahub.js';
$cssVer = is_file($cssPath) ? filemtime($cssPath) : time();
$jsVer = is_file($jsPath) ? filemtime($jsPath) : time();
echo '<link rel="stylesheet" href="' . htmlspecialchars($pluginUrl) . '/assets/astrahub.css?v=' . $cssVer . '">';
echo '<script src="' . htmlspecialchars($pluginUrl) . '/assets/astrahub.js?v=' . $jsVer . '"></script>';
include 'footer.php';
