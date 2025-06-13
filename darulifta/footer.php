    <!-- Islamic decorative elements -->
    <div class="islamic-pattern"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <script>
        // Toggle functions
        const dropdown = document.getElementById("profileDropdown");
        const sideOverlay = document.getElementById("sideOverlay");
        const hamburger = document.getElementById("hamburger");
        const profilePic = document.getElementById("profilePic");
        
        // Toggle dropdown menu
        profilePic.addEventListener("click", function(e) {
            e.stopPropagation();
            dropdown.classList.toggle("show");
            if (sideOverlay.classList.contains("show")) {
                sideOverlay.classList.remove("show");
                hamburger.classList.remove("active");
            }
        });
        
        // Toggle side menu
        hamburger.addEventListener("click", function(e) {
            e.stopPropagation();
            hamburger.classList.toggle("active");
            sideOverlay.classList.toggle("show");
            if (dropdown.classList.contains("show")) {
                dropdown.classList.remove("show");
            }
        });
        
        // Close menus when clicking outside
        document.addEventListener("click", function(e) {
            if (!dropdown.contains(e.target) && e.target !== profilePic) {
                dropdown.classList.remove("show");
            }
            if (!sideOverlay.contains(e.target) && e.target !== hamburger) {
                sideOverlay.classList.remove("show");
                hamburger.classList.remove("active");
            }
        });
        
        // Close menus with Escape key
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                dropdown.classList.remove("show");
                sideOverlay.classList.remove("show");
                hamburger.classList.remove("active");
            }
        });
    </script>
</body>
</html>