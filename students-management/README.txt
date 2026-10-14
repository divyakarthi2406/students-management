========================================================================
       STUDENT PROFILE MANAGEMENT SYSTEM - COLLEGE ASSIGNMENT
========================================================================
Built with: Core PHP, MySQL, HTML5, CSS3, Vanilla JavaScript
Compatible with: XAMPP on Windows (Apache & MySQL)
Project Directory: c:\xampp1\htdocs\students-management\

------------------------------------------------------------------------
1. PROJECT OVERVIEW
------------------------------------------------------------------------
The Student Profile Management System is a full-featured web application
designed for managing student academic records, profiles, and associated
documentation (Profile Photos, Govt/College ID Proofs, and Certificates).

Key Highlights:
- Core PHP (No Laravel, Node.js, or external frameworks).
- Strict Prepared Statements to prevent SQL Injection.
- Context-aware HTML escaping to prevent Cross-Site Scripting (XSS).
- Deep MIME inspection using PHP Fileinfo (finfo) to prevent malicious files.
- Secure PHP proxy file-serving endpoint (download.php) protecting sensitive
  documents from direct web exposure via Apache .htaccess.
- Clean, responsive dashboard UI with interactive statistics, modal dialogs,
  and drag-and-drop document upload handlers.

------------------------------------------------------------------------
2. DATABASE CONFIGURATION
------------------------------------------------------------------------
- MySQL Host:     localhost
- MySQL Port:     3306
- Database Name:  student_management
- Username:       root
- Password:       (empty string / none)

Tables utilized:
1. students:
   - id (INT, Primary Key, Auto Increment)
   - name (VARCHAR 100)
   - roll_number (VARCHAR 30, Unique)
   - email (VARCHAR 100)
   - course (VARCHAR 100)
   - created_at (TIMESTAMP)

2. student_files:
   - id (INT, Primary Key, Auto Increment)
   - student_id (INT, Foreign Key referencing students.id)
   - original_name (VARCHAR 255)
   - stored_name (VARCHAR 255)
   - file_path (VARCHAR 500)
   - file_type (VARCHAR 100)
   - file_size (INT)
   - uploaded_at (TIMESTAMP)

------------------------------------------------------------------------
3. HOW TO RUN THE PROJECT IN XAMPP
------------------------------------------------------------------------
Step 1: Open the XAMPP Control Panel.
Step 2: Ensure "Apache" and "MySQL" modules are running (green status).
Step 3: Open any web browser (Google Chrome, Microsoft Edge, Firefox).
Step 4: Navigate to:
        http://localhost/students-management/

------------------------------------------------------------------------
4. STEP-BY-STEP FEATURE TESTING GUIDE
------------------------------------------------------------------------

Feature 1: Dashboard Overview (index.php)
- Visit http://localhost/students-management/
- Verify KPI summary cards: Total Students, Documents Stored, Courses, Storage Used.
- Check Course Distribution breakdown and System & Security status.

Feature 2: Add New Student (add.php)
- Click "+ Add Student" from the top navigation bar.
- Fill in:
  * Full Name: e.g., "Aarav Sharma"
  * Roll Number: e.g., "CS-2026-001"
  * Email: e.g., "aarav.sharma@example.com"
  * Course: e.g., "B.Tech Computer Science & Engineering"
- Attach documents:
  * Profile Photo: Select a JPG or PNG image (<= 2 MB). Notice the instant circular preview.
  * ID Proof: Select a PDF or image document (<= 2 MB).
  * Certificates: Select one or multiple certificates (PDF, JPG, PNG).
- Click "Enroll Student Record".
- Verify redirection to the newly created student's profile page with a green success alert.

Feature 3: Unique Roll Number Validation
- Attempt to add another student with the identical roll number "CS-2026-001".
- Observe the server-side rejection message preventing duplicate roll numbers.

Feature 4: File Upload Security & 2 MB Limit Validation
- Try uploading a file larger than 2 MB: Observe immediate client-side alert preventing upload.
- Try uploading an executable or non-permitted file (e.g., .php, .exe):
  Observe rejection by both client-side and PHP fileinfo MIME inspection.

Feature 5: Secure File-Serving & Directory Protection
- Visit a student's profile (view.php?id=1).
- Click the preview icon (eye) next to an image or PDF.
  The file opens safely through download.php?id=1&preview=1.
- Click the download icon. The file downloads with its original name.
- Direct Access Test: Attempt to access the uploads folder directly in the browser:
  http://localhost/students-management/uploads/
  Notice that Apache returns 403 Forbidden due to .htaccess protection.

Feature 6: View & Filter Students Directory (students.php)
- Click "View Students" in the navigation bar.
- Search by student name, roll number, or email in real-time.
- Filter by specific course using the dropdown.
- Sort records by Newest First, Oldest First, Name A-Z, or Roll Number.

Feature 7: Edit Student Profile (edit.php)
- On any student profile or directory row, click "Edit".
- Change student details (e.g., update email or course).
- Notice that existing files are listed with their categories and sizes.
- Upload additional certificates or replace the profile photo.
- Save changes: All existing files remain intact while new documents are appended.

Feature 8: Delete Student or Document (delete.php)
- To delete an individual document: On view.php or edit.php, click the remove button next to a file. Confirm deletion. The file is removed from disk and database metadata.
- To delete a student record: Click "Delete" on the student profile or table row. Confirm deletion in the modal dialog. The student record, all their files from the disk, and metadata are safely wiped.

------------------------------------------------------------------------
5. FILE STRUCTURE
------------------------------------------------------------------------
students-management/
├── db.php             (Database PDO connection, CSRF & helper utilities)
├── upload.php         (File upload handler, MIME validation, safe storage)
├── download.php       (Secure proxy file-serving endpoint with path checks)
├── header.php         (Shared HTML header, navigation bar, flash alerts)
├── footer.php         (Shared HTML footer, confirmation modal dialog)
├── index.php          (Modern admin dashboard with stats & recent students)
├── students.php       (Student directory with search, course filters & sorting)
├── add.php            (Student enrollment form with file upload dropzones)
├── view.php           (Comprehensive student details & document viewer)
├── edit.php           (Update student information & document manager)
├── delete.php         (Safe transaction-based student & file deletion)
├── style.css          (Modern, responsive design with CSS variables)
├── script.js          (Form validation, live previews & modal logic)
├── uploads/           (Secure server storage for uploaded documents)
│   ├── .htaccess      (Blocks direct HTTP directory browsing & execution)
│   └── index.html     (Fallback 403 Forbidden page)
└── README.txt         (Setup, security documentation & testing guide)
========================================================================
