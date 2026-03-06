'use strict';

document.addEventListener('DOMContentLoaded', function () {

    /* ── NOTIFICATION BELL ─────────────────────────────── */
    var BASE  = (typeof ANU_BASE !== 'undefined') ? ANU_BASE : '../';
    var AJAX  = BASE + 'includes/notif_ajax.php';
    var isOpen = false;
    var prevCount = 0;
    var dropdown = document.getElementById('notifDropdown');
    var badge    = document.getElementById('notifCount');

    window.notifToggle = function (e) {
        if (e) { e.stopPropagation(); e.preventDefault(); }
        if (!dropdown) return;
        isOpen = !isOpen;
        dropdown.style.display = isOpen ? 'block' : 'none';
        if (isOpen) poll();
    };

    document.addEventListener('click', function (e) {
        if (!isOpen) return;
        var wrap = document.getElementById('notifBellWrap');
        if (wrap && !wrap.contains(e.target)) {
            isOpen = false;
            if (dropdown) dropdown.style.display = 'none';
        }
    });

    function setCount(n) {
        if (!badge) return;
        badge.textContent = n > 99 ? '99+' : n;
        badge.style.display = n > 0 ? 'flex' : 'none';
    }

    function poll() {
        fetch(AJAX + '?action=poll&_=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || d.error) return;
                var n = d.count || 0;
                if (n > prevCount && d.notifications && d.notifications.length) {
                    var x = d.notifications[0];
                    if (!x.is_read) showToast(x.title, x.message, x.icon, x.color);
                }
                prevCount = n;
                setCount(n);
                if (isOpen) renderList(d.notifications || []);
            }).catch(function(){});
    }

    window.notifMarkAll = function () {
        fetch(AJAX + '?action=mark_all', { method:'POST' })
            .then(function(r){return r.json();})
            .then(function(){ prevCount=0; setCount(0);
                document.querySelectorAll('#notifList a').forEach(function(a){
                    a.style.background='#fff'; a.style.borderLeft='none';});
            }).catch(function(){});
    };

    window.notifMarkOne = function (id) {
        fetch(AJAX + '?action=mark_one&id=' + id)
            .then(function(r){return r.json();})
            .then(function(d){ prevCount=d.count||0; setCount(prevCount);
                var el=document.querySelector('#notifList a[data-id="'+id+'"]');
                if(el){el.style.background='#fff';el.style.borderLeft='none';}
            }).catch(function(){});
    };

    function renderList(items) {
        var list = document.getElementById('notifList');
        if (!list) return;
        if (!items || !items.length) {
            list.innerHTML = '<div style="padding:28px;text-align:center;color:#bbb;font-size:13px;"><i class="bi bi-bell-slash" style="font-size:2rem;display:block;margin-bottom:8px;color:#ddd;"></i>No notifications yet</div>';
            return;
        }
        list.innerHTML = items.map(function(n){
            var col=n.color||'#ff0000', ico=n.icon||'bi-bell';
            var bg=n.is_read?'#fff':'#fff8f8', bl=n.is_read?'none':'3px solid #ff0000';
            return '<a href="'+esc(n.link||'#')+'" data-id="'+n.id+'" onclick="notifMarkOne('+n.id+')" '+
                'style="display:flex;gap:10px;align-items:flex-start;padding:10px 16px;border-bottom:1px solid #f8f8f8;text-decoration:none;color:#333;background:'+bg+';border-left:'+bl+';">'+
                '<div style="width:34px;height:34px;border-radius:50%;background:'+col+'22;color:'+col+';display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;"><i class="bi '+esc(ico)+'"></i></div>'+
                '<div style="flex:1;min-width:0;">'+
                '<div style="font-size:13px;font-weight:700;margin-bottom:2px;">'+esc(n.title)+'</div>'+
                '<div style="font-size:12px;color:#666;">'+esc(n.message)+'</div>'+
                '<div style="font-size:10px;color:#aaa;margin-top:3px;">'+esc(n.time_ago||'')+'</div>'+
                '</div></a>';
        }).join('');
    }

    if (document.getElementById('notifBellBtn')) {
        setTimeout(poll, 3000);
        setInterval(poll, 30000);
    }

});

window.showToast = function (title, msg, icon, color, dur) {
    dur=dur||5000; icon=icon||'bi-bell-fill'; color=color||'#ff0000';
    var wrap=document.getElementById('anuToastWrap');
    if(!wrap){wrap=document.createElement('div');wrap.id='anuToastWrap';
        wrap.style.cssText='position:fixed;top:70px;right:16px;z-index:999999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
        document.body.appendChild(wrap);}
    var t=document.createElement('div');
    t.style.cssText='background:#fff;border-radius:12px;padding:13px 16px;box-shadow:0 6px 24px rgba(0,0,0,.16);font-size:13px;display:flex;align-items:flex-start;gap:11px;pointer-events:all;border-left:4px solid '+color+';min-width:240px;max-width:320px;';
    t.innerHTML='<div style="font-size:18px;color:'+color+';flex-shrink:0;"><i class="bi '+esc(icon)+'"></i></div>'+
        '<div style="flex:1;"><div style="font-weight:700;margin-bottom:2px;">'+esc(title)+'</div><div style="color:#666;font-size:12px;">'+esc(msg)+'</div></div>'+
        '<button onclick="this.parentElement.remove()" style="background:none;border:none;color:#bbb;cursor:pointer;font-size:18px;padding:0;line-height:1;">×</button>';
    wrap.appendChild(t);
    setTimeout(function(){t.style.transition='all .3s ease';t.style.opacity='0';t.style.transform='translateX(40px)';
        setTimeout(function(){if(t.parentNode)t.remove();},350);},dur);
};

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
