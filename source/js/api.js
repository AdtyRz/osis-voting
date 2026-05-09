/**
 * api.js — Helper API untuk Frontend Siswa OSIS Voting
 * Include file ini di semua halaman HTML frontend
 */

const API_BASE = 'api/frontend';

// ═══════════════════════════════════════════
// SESSION / AUTH HELPERS
// ═══════════════════════════════════════════

function getSession() {
  try {
    return JSON.parse(sessionStorage.getItem('osis_user') || 'null');
  } catch { return null; }
}

function setSession(user) {
  sessionStorage.setItem('osis_user', JSON.stringify(user));
}

function clearSession() {
  sessionStorage.removeItem('osis_user');
}

function requireLogin() {
  if (!getSession()) {
    window.location.href = 'login.html';
    return false;
  }
  return true;
}

// ═══════════════════════════════════════════
// API CALLS
// ═══════════════════════════════════════════

async function apiLogin(nisn, password) {
  const res = await fetch(`${API_BASE}/login.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ nisn, password }),
    credentials: 'include',
  });
  return res.json();
}

async function apiLogout() {
  await fetch(`${API_BASE}/logout.php`, { method: 'POST', credentials: 'include' });
  clearSession();
  window.location.href = 'login.html';
}

async function apiGetPaslon() {
  const res = await fetch(`${API_BASE}/paslon.php`, { credentials: 'include' });
  return res.json();
}

async function apiVote(paslonId) {
  const res = await fetch(`${API_BASE}/vote.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ paslon_id: paslonId }),
    credentials: 'include',
  });
  return res.json();
}

// ═══════════════════════════════════════════
// TOAST NOTIFIKASI
// ═══════════════════════════════════════════

function showToast(msg, type = 'success') {
  // Buat container jika belum ada
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = `
      position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%);
      z-index: 9999; display: flex; flex-direction: column; gap: 8px;
      width: 90%; max-width: 380px; pointer-events: none;
    `;
    document.body.appendChild(container);
  }

  const colors = {
    success: { bg: '#e8f5e9', border: '#4caf50', color: '#1b5e20', icon: '✓' },
    error:   { bg: '#ffebee', border: '#ef5350', color: '#b71c1c', icon: '✕' },
    info:    { bg: '#e3f2fd', border: '#42a5f5', color: '#0d47a1', icon: 'ℹ' },
    warning: { bg: '#fff8e1', border: '#ffca28', color: '#f57f17', icon: '⚠' },
  };
  const c = colors[type] || colors.info;

  const toast = document.createElement('div');
  toast.style.cssText = `
    background: ${c.bg}; border: 1.5px solid ${c.border}; color: ${c.color};
    padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
    display: flex; align-items: center; gap: 10px; pointer-events: all;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    animation: toastIn .25s ease; font-family: 'Poppins', sans-serif;
  `;
  toast.innerHTML = `<span style="font-size:16px">${c.icon}</span><span>${msg}</span>`;

  // Inject keyframe jika belum ada
  if (!document.getElementById('toast-style')) {
    const s = document.createElement('style');
    s.id = 'toast-style';
    s.textContent = '@keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}';
    document.head.appendChild(s);
  }

  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

// ═══════════════════════════════════════════
// MODAL KONFIRMASI VOTING
// ═══════════════════════════════════════════

function showVoteModal(paslon, onConfirm) {
  // Hapus modal lama jika ada
  document.getElementById('vote-modal')?.remove();

  const modal = document.createElement('div');
  modal.id = 'vote-modal';
  modal.style.cssText = `
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 9998; display: flex; align-items: center; justify-content: center;
    padding: 20px; animation: toastIn .2s ease; font-family: 'Poppins', sans-serif;
  `;
  modal.innerHTML = `
    <div style="
      background: #fffdf7; border-radius: 20px; padding: 30px 24px;
      width: 100%; max-width: 340px; text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    ">
      <div style="
        width: 70px; height: 70px; border-radius: 50%; overflow: hidden;
        margin: 0 auto 16px; border: 3px solid #1a1a2e;
      ">
        <img src="${paslon.foto_url}" style="width:100%;height:100%;object-fit:cover"
          onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(paslon.nama_ketua)}&background=1a1a2e&color=fff'">
      </div>
      <div style="font-size:11px;letter-spacing:2px;color:#888;text-transform:uppercase;margin-bottom:4px">Paslon ${paslon.nomor_urut}</div>
      <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#1a1a2e;margin-bottom:6px">
        ${paslon.nama_ketua} & ${paslon.nama_wakil}
      </div>
      <p style="font-size:12px;color:#666;line-height:1.6;margin-bottom:24px">
        Apakah kamu yakin memilih paslon ini?<br>
        <strong style="color:#c0392b">Pilihan tidak dapat diubah.</strong>
      </p>
      <div style="display:flex;gap:10px">
        <button id="modal-cancel" style="
          flex:1;padding:12px;border-radius:10px;border:1.5px solid #ddd;
          background:none;font-size:13px;font-weight:600;color:#666;cursor:pointer;
          font-family:'Poppins',sans-serif;
        ">Batal</button>
        <button id="modal-confirm" style="
          flex:1;padding:12px;border-radius:10px;border:none;
          background:#1a1a2e;color:#fff;font-size:13px;font-weight:600;cursor:pointer;
          font-family:'Poppins',sans-serif;
        ">Ya, Pilih!</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
  document.getElementById('modal-cancel').onclick  = () => modal.remove();
  document.getElementById('modal-confirm').onclick = () => { modal.remove(); onConfirm(); };
  modal.onclick = e => { if (e.target === modal) modal.remove(); };
}
