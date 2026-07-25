/* ==========================================================================
   StudentHub — Authentication & Role Simulation Manager
   ========================================================================== */

const AuthManager = {
  // Key for local storage
  STORAGE_KEY: "studenthub_active_role",

  // Available roles definition
  roles: {
    student: {
      name: "Alex Mercer",
      role: "student",
      badgeClass: "role-student",
      label: "Student",
      id: "STU-2026-8891",
      email: "alex.mercer@studenthub.edu",
      avatar: "AM"
    },
    faculty: {
      name: "Dr. Alan Roy",
      role: "faculty",
      badgeClass: "role-faculty",
      label: "Faculty",
      id: "FAC-1004-902",
      email: "alan.roy@studenthub.edu",
      avatar: "AR"
    },
    admin: {
      name: "System Administrator",
      role: "admin",
      badgeClass: "role-admin",
      label: "Admin",
      id: "ADM-0001-001",
      email: "admin@studenthub.edu",
      avatar: "SA"
    }
  },

  // Initialize role session
  getCurrentUser() {
    const savedRole = localStorage.getItem(this.STORAGE_KEY) || "student";
    return this.roles[savedRole] || this.roles.student;
  },

  // Switch role explicitly
  switchRole(roleName) {
    if (this.roles[roleName]) {
      localStorage.setItem(this.STORAGE_KEY, roleName);
      // Reload current page to update role context
      window.location.reload();
    }
  },

  // Update Header UI with current role info
  renderHeaderRole() {
    const user = this.getCurrentUser();
    const roleBadgeElem = document.getElementById("headerRoleBadge");
    const avatarElem = document.getElementById("headerAvatar");
    const userNameElem = document.getElementById("headerUserName");

    if (roleBadgeElem) {
      roleBadgeElem.textContent = user.label;
      roleBadgeElem.className = `role-badge ${user.badgeClass}`;
    }
    if (avatarElem) {
      avatarElem.textContent = user.avatar;
    }
    if (userNameElem) {
      userNameElem.textContent = user.name;
    }
  }
};

document.addEventListener("DOMContentLoaded", () => {
  AuthManager.renderHeaderRole();
});
