/**
 * AstraHub Friends Widget - 友链墙前台组件
 * 零依赖，自注入 CSS，从自身 script src 推导 API 地址
 *
 * 使用方法：
 * <div id="astrahub-friends"></div>
 * <script src="/usr/plugins/AstraHub/widget/friends-widget.js" defer></script>
 */
(function() {
  'use strict';

  function getApiBase() {
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].src || '';
      if (src.indexOf('friends-widget.js') !== -1) {
        var match = src.match(/^(https?:\/\/[^\/]+)/);
        if (match) {
          return match[1] + '/index.php/action/astrahub';
        }
      }
    }
    return window.location.origin + '/index.php/action/astrahub';
  }

  var API_BASE = getApiBase();

  function injectStyles() {
    var scripts = document.getElementsByTagName('script');
    var cssUrl = '';
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].src || '';
      if (src.indexOf('friends-widget.js') !== -1) {
        cssUrl = src.replace('friends-widget.js', 'friends-widget.css');
        break;
      }
    }
    if (cssUrl) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = cssUrl;
      document.head.appendChild(link);
    }
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function defaultAvatar(name) {
    var text = (name || '?').charAt(0).toUpperCase();
    var colors = ['#0061a4', '#4a7cc9', '#16a34a', '#ea580c', '#7c3aed', '#db2777'];
    var color = colors[Math.abs(hashCode(name || '')) % colors.length];
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 56 56">'
      + '<rect width="56" height="56" fill="' + color + '"/>'
      + '<text x="28" y="36" text-anchor="middle" fill="#fff" font-size="24" font-weight="600" font-family="sans-serif">'
      + escapeHtml(text) + '</text></svg>';
    return 'data:image/svg+xml,' + encodeURIComponent(svg);
  }

  function hashCode(str) {
    var hash = 0;
    for (var i = 0; i < str.length; i++) {
      hash = ((hash << 5) - hash) + str.charCodeAt(i);
      hash |= 0;
    }
    return hash;
  }

  function renderCard(link) {
    var avatar = link.avatarUrl || defaultAvatar(link.siteName);
    return '<a class="ah-friend-card" href="' + escapeHtml(link.siteUrl) + '" target="_blank" rel="noopener nofollow">'
      + '<img class="ah-friend-avatar" src="' + escapeHtml(avatar) + '" alt="" onerror="this.src=\'' + defaultAvatar(link.siteName).replace(/'/g, "\\'") + '\'">'
      + '<div class="ah-friend-info">'
      + '<p class="ah-friend-name">' + escapeHtml(link.siteName) + '</p>'
      + '<p class="ah-friend-summary">' + escapeHtml(link.summary || '暂无描述') + '</p>'
      + '</div></a>';
  }

  function renderModal() {
    return '<div class="ah-modal-overlay" id="ah-apply-modal" style="display:none">'
      + '<div class="ah-modal-content">'
      + '<div class="ah-modal-header">'
      + '<h3 class="ah-modal-title">申请友链</h3>'
      + '<button class="ah-modal-close" type="button" aria-label="关闭">'
      + '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">'
      + '<path d="M5 5l10 10M15 5l-10 10"/></svg></button>'
      + '</div>'
      + '<div class="ah-modal-body">'
      + '<div id="ah-apply-message"></div>'
      + '<form id="ah-apply-form">'
      + '<div class="ah-form-group">'
      + '<label class="ah-form-label">站点名称<span class="ah-required">*</span></label>'
      + '<input type="text" class="ah-form-input" name="siteName" required placeholder="输入你的站点名称" maxlength="200">'
      + '</div>'
      + '<div class="ah-form-group">'
      + '<label class="ah-form-label">站点链接<span class="ah-required">*</span></label>'
      + '<input type="url" class="ah-form-input" name="siteUrl" required placeholder="https://example.com" maxlength="512">'
      + '</div>'
      + '<div class="ah-form-group">'
      + '<label class="ah-form-label">联系邮箱<span class="ah-required">*</span></label>'
      + '<input type="email" class="ah-form-input" name="applicantEmail" required placeholder="your@email.com" maxlength="150">'
      + '<p class="ah-form-hint">用于接收审核结果通知</p>'
      + '</div>'
      + '<div class="ah-form-group">'
      + '<label class="ah-form-label">头像链接<span class="ah-required">*</span></label>'
      + '<input type="url" class="ah-form-input" name="avatarUrl" required placeholder="https://example.com/avatar.png" maxlength="512">'
      + '</div>'
      + '<div class="ah-form-group">'
      + '<label class="ah-form-label">RSS 链接<span class="ah-required">*</span></label>'
      + '<input type="url" class="ah-form-input" name="rssUrl" required placeholder="https://example.com/rss" maxlength="512">'
      + '</div>'
      + '<div class="ah-form-group">'
      + '<label class="ah-form-label">站点描述<span class="ah-required">*</span></label>'
      + '<textarea class="ah-form-input ah-form-textarea" name="summary" required placeholder="简单介绍一下你的站点" maxlength="500"></textarea>'
      + '</div>'
      + '<div class="ah-honeypot">'
      + '<label>请勿填写<input type="text" name="ah_hp" tabindex="-1" autocomplete="off"></label>'
      + '</div>'
      + '<button type="submit" class="ah-form-submit" id="ah-submit-btn">提交申请</button>'
      + '</form>'
      + '</div></div></div>';
  }

  function showMessage(container, type, text) {
    var msgEl = document.getElementById('ah-apply-message');
    if (msgEl) {
      msgEl.innerHTML = '<div class="ah-message ah-message-' + type + '">' + escapeHtml(text) + '</div>';
    }
  }

  function init() {
    var container = document.getElementById('astrahub-friends');
    if (!container) return;

    injectStyles();

    container.innerHTML = '<div class="ah-friends-container">'
      + '<div class="ah-friends-header">'
      + '<h2 class="ah-friends-title">友情链接</h2>'
      + '<button class="ah-friends-apply-btn" id="ah-apply-btn">'
      + '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>'
      + '申请友链</button>'
      + '</div>'
      + '<div id="ah-friends-list" class="ah-friends-loading">加载中...</div>'
      + '</div>'
      + renderModal();

    var listEl = document.getElementById('ah-friends-list');

    fetch(API_BASE + '?do=friendsPublic')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success && data.items && data.items.length > 0) {
          var html = '<div class="ah-friends-grid">';
          for (var i = 0; i < data.items.length; i++) {
            html += renderCard(data.items[i]);
          }
          html += '</div>';
          listEl.innerHTML = html;
          listEl.className = '';
        } else {
          listEl.innerHTML = '<div class="ah-friends-empty">暂无友链</div>';
          listEl.className = '';
        }
      })
      .catch(function() {
        listEl.innerHTML = '<div class="ah-friends-empty">加载失败</div>';
        listEl.className = '';
      });

    var modal = document.getElementById('ah-apply-modal');
    var applyBtn = document.getElementById('ah-apply-btn');
    var closeBtn = modal ? modal.querySelector('.ah-modal-close') : null;
    var form = document.getElementById('ah-apply-form');
    var submitBtn = document.getElementById('ah-submit-btn');

    function openModal() {
      if (modal) {
        modal.style.display = 'flex';
        void modal.offsetWidth;
        modal.classList.add('ah-modal-visible');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeModal() {
      if (modal) {
        modal.classList.remove('ah-modal-visible');
        document.body.style.overflow = '';
        setTimeout(function() {
          if (!modal.classList.contains('ah-modal-visible')) {
            modal.style.display = 'none';
          }
        }, 220);
      }
    }

    if (applyBtn) applyBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
      });
    }

    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(form);
        var data = {};
        formData.forEach(function(value, key) {
          data[key] = value;
        });

        submitBtn.disabled = true;
        submitBtn.textContent = '提交中...';

        fetch(API_BASE + '?do=friendApply', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
          submitBtn.disabled = false;
          submitBtn.textContent = '提交申请';

          if (result.success) {
            showMessage(null, 'success', '申请已提交，等待站长审核。审核结果将发送至你的邮箱。');
            form.reset();
          } else {
            var msg = result.message || '提交失败';
            if (msg === 'duplicate') msg = '该站点链接已存在，请勿重复提交';
            else if (msg === 'rate limited') msg = '提交过于频繁，请稍后再试';
            else if (msg === 'self link not allowed') msg = '不能添加自己站点的链接';
            else if (msg === 'email required') msg = '请填写有效的联系邮箱';
            else if (msg === 'siteUrl invalid') msg = '请填写有效的站点链接（http/https）';
            else if (msg === 'siteName required') msg = '请填写站点名称';
            showMessage(null, 'error', msg);
          }
        })
        .catch(function() {
          submitBtn.disabled = false;
          submitBtn.textContent = '提交申请';
          showMessage(null, 'error', '网络错误，请稍后重试');
        });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
