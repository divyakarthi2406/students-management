    </main>

    <!-- App Footer -->
    <footer class="app-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <strong>Student Profile Management System</strong>
                <span>College Web Technology Assignment</span>
            </div>
            <div class="footer-meta">
                <span>Built with Core PHP, MySQL &amp; Vanilla JS</span>
                <span>Running on XAMPP Windows</span>
            </div>
        </div>
    </footer>

    <!-- Global Confirmation Modal Dialog -->
    <div class="modal-overlay" id="confirmModal" aria-hidden="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-icon-badge" id="modalIconBadge">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
                <button type="button" class="modal-close" onclick="closeModal()" aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="modalMessage">Are you sure you want to proceed with this action?</p>
                <div class="modal-details" id="modalDetails" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <form id="modalActionForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="id" id="modalTargetId" value="">
                    <button type="submit" class="btn btn-danger" id="modalConfirmBtn">Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Application Script -->
    <script src="script.js"></script>
</body>
</html>
