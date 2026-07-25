/* ==========================================================================
   StudentHub — Master Application UI Controller
   ========================================================================== */

document.addEventListener("DOMContentLoaded", () => {
  AppController.init();
});

const AppController = {
  init() {
    this.setupWireframeMode();
    this.highlightActiveNav();
    this.setupModals();
  },

  // --------------------------------------------------------------------------
  // Wireframe Blueprint Mode Toggle
  // --------------------------------------------------------------------------
  setupWireframeMode() {
    const isWireframe = localStorage.getItem("studenthub_wireframe_mode") === "true";
    if (isWireframe) {
      document.body.classList.add("wireframe-mode");
    }

    const toggleBtns = document.querySelectorAll(".btn-wireframe-toggle");
    toggleBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        const currentlyActive = document.body.classList.contains("wireframe-mode");
        if (currentlyActive) {
          document.body.classList.remove("wireframe-mode");
          localStorage.setItem("studenthub_wireframe_mode", "false");
          this.showToast("Switched to Modern Production Mode 🎨");
        } else {
          document.body.classList.add("wireframe-mode");
          localStorage.setItem("studenthub_wireframe_mode", "true");
          this.showToast("Switched to Low-Fidelity Blueprint Wireframe Mode 📐");
        }
      });
    });
  },

  // --------------------------------------------------------------------------
  // Highlight current page in navbar
  // --------------------------------------------------------------------------
  highlightActiveNav() {
    const currentPath = window.location.pathname.split("/").pop() || "index.html";
    const navItems = document.querySelectorAll(".nav-item");

    navItems.forEach((item) => {
      const link = item.querySelector("a");
      if (link) {
        const href = link.getAttribute("href");
        if (href === currentPath || (currentPath === "" && href === "index.html")) {
          item.classList.add("active");
        } else {
          item.classList.remove("active");
        }
      }
    });
  },

  // --------------------------------------------------------------------------
  // Modal dialog handler
  // --------------------------------------------------------------------------
  setupModals() {
    const closeBtns = document.querySelectorAll(".close-modal-btn, [data-close-modal]");
    closeBtns.forEach((btn) => {
      btn.addEventListener("click", () => {
        const modal = btn.closest(".modal-overlay");
        if (modal) {
          modal.classList.remove("active");
        }
      });
    });

    // Close on overlay click
    const overlays = document.querySelectorAll(".modal-overlay");
    overlays.forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
          overlay.classList.remove("active");
        }
      });
    });
  },

  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add("active");
    }
  },

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove("active");
    }
  },

  // --------------------------------------------------------------------------
  // Toast Notification System
  // --------------------------------------------------------------------------
  showToast(message, type = "info") {
    let toastContainer = document.getElementById("toastContainer");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.id = "toastContainer";
      toastContainer.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
      `;
      document.body.appendChild(toastContainer);
    }

    const toast = document.createElement("div");
    toast.style.cssText = `
      background: #1e293b;
      color: #ffffff;
      border: 1px solid rgba(99, 102, 241, 0.4);
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 0.88rem;
      font-weight: 600;
      box-shadow: 0 10px 25px rgba(0,0,0,0.5);
      animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transition = "opacity 0.3s ease";
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }
};
