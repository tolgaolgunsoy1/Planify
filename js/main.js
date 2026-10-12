/**
 * Planify Task Board - Client-Side JavaScript
 * Topic D2: IMS207 / IMS301 Web Design and Internet Programming
 * Requirement 3: Client-side form validation and rich DOM interactions
 * Features:
 *  - Mobile navigation drawer toggle
 *  - Client-side form validation (Register & Task forms)
 *  - Live character counter on text inputs
 *  - Real-time image preview before upload
 *  - Interactive Drag & Drop Kanban column moves (with AJAX POST)
 *  - Live client-side search/filter on task list
 */

document.addEventListener("DOMContentLoaded", function () {
  initMobileNav();
  initFormValidation();
  initCharacterCounters();
  initImagePreviews();
  initKanbanDragAndDrop();
  initLiveTableSearch();
});

/* ==========================================================================
   1. Mobile Navigation Drawer Toggle (DOM Interaction)
   ========================================================================== */
function initMobileNav() {
  const toggleBtn = document.getElementById("navToggle");
  const siteNav = document.getElementById("siteNav");

  if (toggleBtn && siteNav) {
    toggleBtn.addEventListener("click", function () {
      siteNav.classList.toggle("open");
    });
  }
}

/* ==========================================================================
   2. Client-Side Form Validation (Requirement 3)
   ========================================================================== */
function initFormValidation() {
  // Register Form Validation
  const registerForm = document.getElementById("registerForm");
  if (registerForm) {
    registerForm.addEventListener("submit", function (e) {
      let isValid = true;

      const username = document.getElementById("reg_username");
      const email = document.getElementById("reg_email");
      const password = document.getElementById("reg_password");
      const passwordConfirm = document.getElementById("reg_password_confirm");

      // Reset feedback
      clearAllFeedback(registerForm);

      // Validate Username
      if (username && username.value.trim().length < 3) {
        showFeedback(username, "Username must be at least 3 characters long.", "error");
        isValid = false;
      }

      // Validate Email
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (email && !emailRegex.test(email.value.trim())) {
        showFeedback(email, "Please provide a valid email address.", "error");
        isValid = false;
      }

      // Validate Password
      if (password && password.value.length < 6) {
        showFeedback(password, "Password must be at least 6 characters long.", "error");
        isValid = false;
      }

      // Confirm Password match
      if (password && passwordConfirm && password.value !== passwordConfirm.value) {
        showFeedback(passwordConfirm, "Passwords do not match.", "error");
        isValid = false;
      }

      if (!isValid) {
        e.preventDefault();
      }
    });
  }

  // Task Form Validation (Create & Edit)
  const taskForm = document.getElementById("taskForm");
  if (taskForm) {
    taskForm.addEventListener("submit", function (e) {
      let isValid = true;
      const titleInput = document.getElementById("task_title");
      const projectSelect = document.getElementById("task_project");
      const fileInput = document.getElementById("task_attachment");

      clearAllFeedback(taskForm);

      if (titleInput && titleInput.value.trim().length < 2) {
        showFeedback(titleInput, "Task title must be at least 2 characters.", "error");
        isValid = false;
      }

      if (projectSelect && projectSelect.value === "") {
        showFeedback(projectSelect, "Please select a project for this task.", "error");
        isValid = false;
      }

      // Check file size (<= 1MB per Requirement 9)
      if (fileInput && fileInput.files.length > 0) {
        const file = fileInput.files[0];
        const maxBytes = 1048576; // 1 MB
        if (file.size > maxBytes) {
          showFeedback(fileInput, "File exceeds 1 MB limit! Please choose a smaller image.", "error");
          isValid = false;
        }
      }

      if (!isValid) {
        e.preventDefault();
      }
    });
  }
}

function showFeedback(inputElement, message, type) {
  let feedback = inputElement.parentElement.querySelector(".input-feedback");
  if (!feedback) {
    feedback = document.createElement("div");
    feedback.className = "input-feedback";
    inputElement.parentElement.appendChild(feedback);
  }
  feedback.textContent = message;
  feedback.className = `input-feedback visible ${type}`;
}

function clearAllFeedback(form) {
  const feedbacks = form.querySelectorAll(".input-feedback");
  feedbacks.forEach((el) => {
    el.textContent = "";
    el.classList.remove("visible", "error", "success");
  });
}

/* ==========================================================================
   3. Live Character Counter (DOM Interaction)
   ========================================================================== */
function initCharacterCounters() {
  const countedInputs = document.querySelectorAll("[data-counter-target]");
  countedInputs.forEach((input) => {
    const targetId = input.getAttribute("data-counter-target");
    const counterEl = document.getElementById(targetId);
    const max = input.getAttribute("maxlength") || 255;

    if (counterEl) {
      const updateCount = () => {
        const currentLength = input.value.length;
        counterEl.textContent = `${currentLength} / ${max} characters`;
        if (currentLength >= max * 0.9) {
          counterEl.style.color = "var(--danger)";
        } else {
          counterEl.style.color = "var(--text-muted)";
        }
      };

      input.addEventListener("input", updateCount);
      updateCount(); // Initial count
    }
  });
}

/* ==========================================================================
   4. Live Image Preview Before Upload (DOM Interaction)
   ========================================================================== */
function initImagePreviews() {
  const fileInputs = document.querySelectorAll("input[type='file'][data-preview-img]");
  fileInputs.forEach((input) => {
    const previewId = input.getAttribute("data-preview-img");
    const containerId = input.getAttribute("data-preview-container");
    const previewImg = document.getElementById(previewId);
    const container = document.getElementById(containerId);

    input.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        const file = this.files[0];

        // Ensure it is an image
        if (!file.type.startsWith("image/")) {
          alert("Please select a valid image file (JPG, PNG, WebP).");
          this.value = "";
          return;
        }

        // Check 1MB limit
        if (file.size > 1048576) {
          alert("Selected file is larger than 1 MB! Please choose a smaller image.");
          this.value = "";
          return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
          if (previewImg) {
            previewImg.src = e.target.result;
          }
          if (container) {
            container.style.display = "block";
          }
        };
        reader.readAsDataURL(file);
      }
    });
  });
}

/* ==========================================================================
   5. SIGNATURE FEATURE: Interactive Drag & Drop Kanban Moves
   Tasks move between "to do", "doing", and "done"
   ========================================================================== */
function initKanbanDragAndDrop() {
  const taskCards = document.querySelectorAll(".task-card[draggable='true']");
  const dropContainers = document.querySelectorAll(".column-tasks-container");

  if (taskCards.length === 0 || dropContainers.length === 0) return;

  taskCards.forEach((card) => {
    card.addEventListener("dragstart", function (e) {
      card.classList.add("dragging");
      e.dataTransfer.setData("text/plain", card.getAttribute("data-task-id"));
      e.dataTransfer.effectAllowed = "move";
    });

    card.addEventListener("dragend", function () {
      card.classList.remove("dragging");
    });
  });

  dropContainers.forEach((container) => {
    container.addEventListener("dragover", function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
      container.classList.add("drag-over");
    });

    container.addEventListener("dragleave", function () {
      container.classList.remove("drag-over");
    });

    container.addEventListener("drop", function (e) {
      e.preventDefault();
      container.classList.remove("drag-over");

      const taskId = e.dataTransfer.getData("text/plain");
      const targetStatus = container.getAttribute("data-status");
      const card = document.querySelector(`.task-card[data-task-id='${taskId}']`);

      if (!card || !targetStatus) return;

      const currentStatus = card.getAttribute("data-current-status");
      if (currentStatus === targetStatus) return;

      // Optimistically move card in DOM
      container.appendChild(card);
      card.setAttribute("data-current-status", targetStatus);

      // Remove empty state message if present in destination
      const emptyMsg = container.querySelector(".task-empty-state");
      if (emptyMsg) emptyMsg.remove();

      // Update counters in column headers
      updateColumnCounters();

      // Persist status change to database via AJAX POST to task-move.php
      const formData = new FormData();
      formData.append("task_id", taskId);
      formData.append("new_status", targetStatus);
      formData.append("ajax", "1");

      fetch("task-move.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (!data.success) {
            alert("Error moving task: " + (data.error || "Unknown server error"));
            window.location.reload();
          } else {
            // Update overdue highlight state dynamically if status changed to 'done'
            if (targetStatus === "done") {
              card.classList.remove("card-overdue");
            }
          }
        })
        .catch((err) => {
          console.error("AJAX task move error:", err);
          // Fallback reload if network failed
          window.location.reload();
        });
    });
  });
}

function updateColumnCounters() {
  document.querySelectorAll(".board-column").forEach((col) => {
    const countBadge = col.querySelector(".column-count");
    const tasks = col.querySelectorAll(".task-card");
    if (countBadge) {
      countBadge.textContent = tasks.length;
    }
  });
}

/* ==========================================================================
   6. Live Search Filter on Task List (DOM Interaction)
   Filters tasks in real time as the user types
   ========================================================================== */
function initLiveTableSearch() {
  const searchInput = document.getElementById("liveTaskSearch");
  const taskTable = document.getElementById("taskTableBody");

  if (searchInput && taskTable) {
    searchInput.addEventListener("input", function () {
      const query = this.value.toLowerCase().trim();
      const rows = taskTable.querySelectorAll("tr.task-row");

      rows.forEach((row) => {
        const title = row.getAttribute("data-title") || "";
        const desc = row.getAttribute("data-desc") || "";
        const project = row.getAttribute("data-project") || "";

        const matches =
          title.toLowerCase().includes(query) ||
          desc.toLowerCase().includes(query) ||
          project.toLowerCase().includes(query);

        if (matches) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });
  }
}
