import os
import sys
from reportlab.lib.pagesizes import letter
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether, HRFlowable
)
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib import colors
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super(NumberedCanvas, self).__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super(NumberedCanvas, self).showPage()
        super(NumberedCanvas, self).save()

    def draw_page_decorations(self, page_count):
        self.saveState()
        self.setFont("Helvetica", 8)
        self.setFillColor(colors.HexColor("#64748B"))
        
        # Suppress headers/footers on cover / first page if desired, but for technical guide page 1 has running decor
        page_w, page_h = letter
        
        # Header (Top)
        self.drawString(54, page_h - 36, "Trackify DMS — Enterprise Database Architecture & Defense Mastery Guide")
        self.setStrokeColor(colors.HexColor("#E2E8F0"))
        self.setLineWidth(0.5)
        self.line(54, page_h - 42, page_w - 54, page_h - 42)
        
        # Footer (Bottom)
        self.line(54, 45, page_w - 54, 45)
        self.drawString(54, 32, "Confidential & Proprietary — Prepared for Capstone / Thesis Defense")
        page_str = f"Page {self._pageNumber} of {page_count}"
        self.drawRightString(page_w - 54, 32, page_str)
        
        self.restoreState()

def build_pdf(filename):
    doc = SimpleDocTemplate(
        filename,
        pagesize=letter,
        leftMargin=54,
        rightMargin=54,
        topMargin=54,
        bottomMargin=54
    )
    
    styles = getSampleStyleSheet()
    
    # Custom Palette
    c_primary = colors.HexColor("#0F172A")    # Deep slate
    c_blue = colors.HexColor("#2563EB")       # Primary brand blue
    c_teal = colors.HexColor("#0D9488")       # Accent teal
    c_dark = colors.HexColor("#1E293B")       # Dark text
    c_muted = colors.HexColor("#64748B")      # Muted grey
    c_bg_light = colors.HexColor("#F8FAFC")   # Light background
    c_border = colors.HexColor("#E2E8F0")     # Subtle border
    c_callout_bg = colors.HexColor("#EFF6FF") # Soft blue callout
    c_callout_border = colors.HexColor("#3B82F6")
    
    # Custom typography
    styles.add(ParagraphStyle('DocTitle', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=24, leading=28, textColor=c_primary, spaceAfter=6))
    styles.add(ParagraphStyle('DocSubtitle', parent=styles['Normal'], fontName='Helvetica', fontSize=12, leading=16, textColor=c_blue, spaceAfter=18))
    styles.add(ParagraphStyle('SecHeading', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=15, leading=19, textColor=c_primary, spaceBefore=14, spaceAfter=8, keepWithNext=True))
    styles.add(ParagraphStyle('SubSecHeading', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=12, leading=16, textColor=c_blue, spaceBefore=10, spaceAfter=4, keepWithNext=True))
    styles.add(ParagraphStyle('SubSubHeading', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=10, leading=14, textColor=c_dark, spaceBefore=6, spaceAfter=3, keepWithNext=True))
    styles.add(ParagraphStyle('CustomBody', parent=styles['Normal'], fontName='Helvetica', fontSize=9, leading=13, textColor=c_dark, spaceAfter=6))
    styles.add(ParagraphStyle('CustomBodyBold', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=9, leading=13, textColor=c_dark, spaceAfter=4))
    styles.add(ParagraphStyle('CustomBullet', parent=styles['Normal'], fontName='Helvetica', fontSize=8.5, leading=12, textColor=c_dark, leftIndent=12, firstLineIndent=-8, spaceAfter=3))
    styles.add(ParagraphStyle('CalloutText', parent=styles['Normal'], fontName='Helvetica', fontSize=8.5, leading=12, textColor=colors.HexColor("#1E3A8A")))
    styles.add(ParagraphStyle('TableHead', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=8, leading=10, textColor=colors.white))
    styles.add(ParagraphStyle('TableCell', parent=styles['Normal'], fontName='Helvetica', fontSize=7.5, leading=10, textColor=c_dark))
    styles.add(ParagraphStyle('TableCellBold', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=7.5, leading=10, textColor=c_dark))
    styles.add(ParagraphStyle('TableCellCode', parent=styles['Normal'], fontName='Courier', fontSize=7, leading=9, textColor=colors.HexColor("#0F172A")))
    styles.add(ParagraphStyle('QTitle', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=9.5, leading=13, textColor=c_blue, spaceBefore=6, spaceAfter=2, keepWithNext=True))
    styles.add(ParagraphStyle('QCategory', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=8, leading=10, textColor=c_teal, spaceAfter=1))
    styles.add(ParagraphStyle('QAnswer', parent=styles['Normal'], fontName='Helvetica', fontSize=8.5, leading=12, textColor=c_dark, spaceAfter=6))

    story = []
    
    # ---------------------------------------------------------
    # COVER / HEADER BLOCK
    # ---------------------------------------------------------
    story.append(Paragraph("Trackify Document Management System", styles['DocTitle']))
    story.append(Paragraph("Master Database Blueprint: Architecture, Integrity, Normalization, Data Flows & Defense Guide", styles['DocSubtitle']))
    
    meta_table_data = [
        [
            Paragraph("<b>Target DBMS:</b> MySQL 8.0+ / MariaDB 10.4+", styles['TableCell']),
            Paragraph("<b>Database Name:</b> <font name='Courier'>dms_db</font>", styles['TableCell']),
            Paragraph("<b>Schema Version:</b> 2.0 Hardened", styles['TableCell']),
            Paragraph("<b>Entity Count:</b> 16 Tables, 1 View", styles['TableCell'])
        ]
    ]
    t_meta = Table(meta_table_data, colWidths=[130, 120, 120, 134])
    t_meta.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), c_bg_light),
        ('BOX', (0,0), (-1,-1), 1, c_border),
        ('INNERGRID', (0,0), (-1,-1), 0.5, c_border),
        ('TOPPADDING', (0,0), (-1,-1), 5),
        ('BOTTOMPADDING', (0,0), (-1,-1), 5),
    ]))
    story.append(t_meta)
    story.append(Spacer(1, 10))
    
    # Executive Callout
    callout_data = [[
        Paragraph(
            "<b>Executive Purpose:</b> This technical guide provides defense candidates, software engineers, and database administrators with a comprehensive, panel-ready understanding of the <b>Trackify (dms_db)</b> schema. It covers relational integrity, ACID compliance, Third Normal Form (3NF) architecture, zero-loss audit logging, multi-stage approval logic, and 18 defense questions with complete model answers.",
            styles['CalloutText']
        )
    ]]
    t_callout = Table(callout_data, colWidths=[504])
    t_callout.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), c_callout_bg),
        ('BOX', (0,0), (-1,-1), 1, c_callout_border),
        ('PADDING', (0,0), (-1,-1), 8),
    ]))
    story.append(t_callout)
    story.append(Spacer(1, 12))
    
    # ---------------------------------------------------------
    # SECTION 1: ARCHITECTURAL OVERVIEW & DATA DICTIONARY
    # ---------------------------------------------------------
    story.append(Paragraph("1. Schema Overview & Data Dictionary", styles['SecHeading']))
    story.append(Paragraph(
        "The database is structured into four cohesive sub-systems: <b>Identity & Access Management (IAM)</b>, <b>Document Ingestion & Version Control</b>, <b>Workflow & Governance</b>, and <b>Audit Trail & Communication</b>. Every table enforces strict typing, declarative foreign key rules, and domain-level validation.",
        styles['CustomBody']
    ))
    
    dict_headers = ["Table Name", "Primary Key", "Core Foreign Keys", "Functional Purpose"]
    dict_rows = [
        [Paragraph(h, styles['TableHead']) for h in dict_headers],
        [
            Paragraph("<b>departments</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("head_user_id → users(id)", styles['TableCell']),
            Paragraph("Defines organizational units (HR, Finance, IT). Links department heads.", styles['TableCell'])
        ],
        [
            Paragraph("<b>users</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("department_id → departments(id)", styles['TableCell']),
            Paragraph("Stores credentials (bcrypt), role (admin, dept_head, staff), lockout, and soft-delete state.", styles['TableCell'])
        ],
        [
            Paragraph("<b>categories</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("None", styles['TableCell']),
            Paragraph("Classifies files (Memo, Contract, Policy). Validates hex color badge with CHECK.", styles['TableCell'])
        ],
        [
            Paragraph("<b>tags</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("None", styles['TableCell']),
            Paragraph("Atomic keyword lookup table eliminating comma-delimited strings (1NF compliance).", styles['TableCell'])
        ],
        [
            Paragraph("<b>documents</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("category_id, department_id, uploaded_by, current_version_id, parent_document_id", styles['TableCell']),
            Paragraph("Central document entity. Manages status, priority, confidentiality, hash, and soft-delete.", styles['TableCell'])
        ],
        [
            Paragraph("<b>document_tags</b>", styles['TableCellBold']),
            Paragraph("(document_id, tag_id)", styles['TableCellCode']),
            Paragraph("document_id → documents<br/>tag_id → tags", styles['TableCell']),
            Paragraph("Associative junction table resolving Many-to-Many (M:N) relationship between documents and tags.", styles['TableCell'])
        ],
        [
            Paragraph("<b>document_versions</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("document_id → documents<br/>uploaded_by → users", styles['TableCell']),
            Paragraph("Immutable revision history. Unique (document_id, version) ensures strict sequential revisions.", styles['TableCell'])
        ],
        [
            Paragraph("<b>document_permissions</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("document_id, user_id, department_id, granted_by", styles['TableCell']),
            Paragraph("Granular per-document Access Control List (ACL) granting read/comment/edit overrides beyond RBAC.", styles['TableCell'])
        ],
        [
            Paragraph("<b>approval_workflows</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("document_id → documents<br/>approver_id → users", styles['TableCell']),
            Paragraph("Multi-stage approval pipeline. Supports approval 'rounds' for resubmission tracking.", styles['TableCell'])
        ],
        [
            Paragraph("<b>action_types</b>", styles['TableCellBold']),
            Paragraph("id (TINYINT AI)", styles['TableCellCode']),
            Paragraph("None", styles['TableCell']),
            Paragraph("Lookup dictionary of all auditable events (upload, approve, reject, login, etc.).", styles['TableCell'])
        ],
        [
            Paragraph("<b>activity_logs</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("user_id → users<br/>document_id → documents<br/>action_type_id → action_types", styles['TableCell']),
            Paragraph("Append-only tamper-proof audit trail. Preserves user_name_snapshot when users are removed.", styles['TableCell'])
        ],
        [
            Paragraph("<b>login_attempts</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("None (Email indexed)", styles['TableCell']),
            Paragraph("Rate limiting and brute-force prevention telemetry. Purged on a 24-hour cycle.", styles['TableCell'])
        ],
        [
            Paragraph("<b>notifications</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("user_id → users<br/>document_id → documents", styles['TableCell']),
            Paragraph("In-app notifications (approvals, mentions, deadlines) with read/unread tracking.", styles['TableCell'])
        ],
        [
            Paragraph("<b>comments</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("document_id → documents<br/>user_id → users", styles['TableCell']),
            Paragraph("Document discussion thread. SET NULL on user deletion prevents dangling or missing remarks.", styles['TableCell'])
        ],
        [
            Paragraph("<b>calendar_tasks</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("department_id → departments<br/>created_by → users", styles['TableCell']),
            Paragraph("Task scheduler. CHECK constraint ensures visibility='department' requires a valid department_id.", styles['TableCell'])
        ],
        [
            Paragraph("<b>document_reference_seq</b>", styles['TableCellBold']),
            Paragraph("id (INT AI)", styles['TableCellCode']),
            Paragraph("None (Unique prefix+year)", styles['TableCell']),
            Paragraph("Atomic counter sequence generator preventing race conditions during reference number generation.", styles['TableCell'])
        ],
        [
            Paragraph("<b>active_documents</b> (View)", styles['TableCellBold']),
            Paragraph("N/A (Virtual Table)", styles['TableCellCode']),
            Paragraph("Points to documents", styles['TableCell']),
            Paragraph("SQL View filtering out deleted_at IS NOT NULL and status='archived' for fast query safety.", styles['TableCell'])
        ]
    ]
    t_dict = Table(dict_rows, colWidths=[90, 70, 144, 200])
    t_dict.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), c_primary),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, c_border),
        ('TOPPADDING', (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, c_bg_light]),
    ]))
    story.append(t_dict)
    story.append(Spacer(1, 14))
    
    # ---------------------------------------------------------
    # SECTION 2: RELATIONAL MAPPING & MULTIPLICITY
    # ---------------------------------------------------------
    story.append(Paragraph("2. Relational Mapping & Multiplicity (Cardinality)", styles['SecHeading']))
    story.append(Paragraph(
        "Multiplicity defines the numerical relationship between occurrences of entities. The Trackify database implements four fundamental multiplicity patterns:",
        styles['CustomBody']
    ))
    
    rel_data = [
        ["Relationship", "Multiplicity", "Foreign Key Location", "Business Rule & Delete Policy"],
        [
            "Department → Users",
            "1 : N (One-to-Many)",
            "users.department_id",
            "A department has multiple employees. If a department is deleted, users are SET NULL (employees are preserved)."
        ],
        [
            "Department → Head User",
            "1 : 1 (One-to-One Optional)",
            "departments.head_user_id",
            "A department may have at most one official Head. Circular reference resolved by creating users before altering departments."
        ],
        [
            "User → Uploaded Documents",
            "1 : N (One-to-Many)",
            "documents.uploaded_by",
            "A user can author many documents. Deleting an active author is RESTRICTed to prevent orphaned corporate files."
        ],
        [
            "Document → Versions",
            "1 : N (One-to-Many)",
            "document_versions.document_id",
            "One document has an audit trail of revisions. Deleting a document CASCADE deletes its revision logs."
        ],
        [
            "Document → Current Version",
            "1 : 1 (One-to-One Direct)",
            "documents.current_version_id",
            "Explicit pointer to the active production revision. Enables instant metadata retrieval without subqueries."
        ],
        [
            "Document → Tags",
            "M : N (Many-to-Many)",
            "document_tags(document_id, tag_id)",
            "A document has many tags; a tag labels many documents. Resolved via composite primary key associative entity."
        ],
        [
            "Document → Approvers",
            "1 : N (One-to-Many per Round)",
            "approval_workflows.document_id",
            "Sequenced pipeline of review steps. Grouped by 'round' to cleanly separate re-submissions after a rejection."
        ],
        [
            "Document → Clones / Forks",
            "1 : N (Self-Referential / Recursive)",
            "documents.parent_document_id",
            "A document can be duplicated or branched into a child document. If parent is deleted, child is SET NULL."
        ]
    ]
    t_rel = Table([[Paragraph(c, styles['TableHead'] if i==0 else styles['TableCell']) for c in row] for i, row in enumerate(rel_data)], colWidths=[110, 80, 134, 180])
    t_rel.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), c_primary),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, c_border),
        ('TOPPADDING', (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, c_bg_light]),
    ]))
    story.append(t_rel)
    story.append(Spacer(1, 14))
    
    # ---------------------------------------------------------
    # SECTION 3: NORMALIZATION DEEP DIVE (1NF TO 3NF)
    # ---------------------------------------------------------
    story.append(Paragraph("3. Normalization Analysis (1NF, 2NF, 3NF & Strategic Denormalization)", styles['SecHeading']))
    story.append(Paragraph(
        "Database normalization eliminates redundant data, prevents insertion/update/deletion anomalies, and ensures data dependency integrity.",
        styles['CustomBody']
    ))
    
    norm_points = [
        "<b>First Normal Form (1NF) — Atomicity & No Repeating Groups:</b> In the legacy schema, tags were stored as a comma-separated string (e.g. <font name='Courier'>'memo,urgent,hr'</font>) in <font name='Courier'>documents.tags</font>. This violates 1NF because individual values cannot be queried, indexed, or foreign-key enforced directly without expensive string pattern matching. The hardened schema achieves 1NF by creating the atomic <font name='Courier'>tags</font> table and the <font name='Courier'>document_tags</font> junction table.",
        "<b>Second Normal Form (2NF) — Full Functional Dependency:</b> 2NF requires 1NF and states that non-key attributes must depend on the <i>entire</i> candidate key, not just a subset. In <font name='Courier'>document_tags</font>, the primary key is composite: <font name='Courier'>(document_id, tag_id)</font>. There are no non-key columns in this table; every attribute constitutes the whole key. In <font name='Courier'>document_versions</font>, all columns (file_path, file_size, notes) depend completely on the synthetic primary key <font name='Courier'>id</font> (or the alternate composite candidate key <font name='Courier'>(document_id, version)</font>).",
        "<b>Third Normal Form (3NF) — Non-Transitive Dependency:</b> 3NF dictates that non-key columns must depend <i>only on the primary key</i> (no column can depend on another non-key column: 'the key, the whole key, and nothing but the key'). For example, document metadata does not store department head name or category color inside <font name='Courier'>documents</font>; it references <font name='Courier'>department_id</font> and <font name='Courier'>category_id</font>, fetching parent details via JOIN.",
        "<b>Strategic Controlled Denormalization (The Audit Exception):</b> Pure 3NF is intentionally relaxed in one critical scenario: <font name='Courier'>activity_logs.user_name_snapshot</font>. In standard 3NF, the actor's name should be queried from <font name='Courier'>users.id</font>. However, in an enterprise compliance environment, if a user account is deleted or legally purged, foreign key constraints would force the audit log to SET NULL. By capturing <font name='Courier'>user_name_snapshot</font> at the time of the event, the audit trail remains permanently readable and legally immutable even if the original user record vanishes."
    ]
    for np in norm_points:
        story.append(Paragraph(f"• {np}", styles['CustomBullet']))
        story.append(Spacer(1, 2))
    story.append(Spacer(1, 10))
    
    # ---------------------------------------------------------
    # SECTION 4: DATA INTEGRITY & DEFENSIVE ARCHITECTURE
    # ---------------------------------------------------------
    story.append(Paragraph("4. Data Integrity & Defensive Constraints", styles['SecHeading']))
    story.append(Paragraph(
        "Integrity constraints prevent invalid, corrupt, or inconsistent data from entering the database regardless of bugs in the front-end application layer.",
        styles['CustomBody']
    ))
    
    integ_data = [
        ["Integrity Type", "Mechanism in Schema", "Defensive Purpose & Edge Case Prevented"],
        [
            "Entity Integrity",
            "PRIMARY KEY + AUTO_INCREMENT on all tables",
            "Guarantees that every tuple has a unique, non-null, immutable row identifier."
        ],
        [
            "Domain Integrity",
            "ENUM('admin','dept_head','staff')<br/>CHECK (color REGEXP '^#[0-9a-fA-F]{6}$')<br/>BIGINT UNSIGNED file_size",
            "Restricts column inputs to legal values. Hex color check stops invalid CSS. BIGINT ensures file size tracking > 2GB without overflow."
        ],
        [
            "State Coherence Integrity",
            "CHECK ((status='pending' AND acted_at IS NULL) OR (status<>'pending' AND acted_at IS NOT NULL))",
            "Enforced on <font name='Courier'>approval_workflows</font>. Prevents invalid review states where an item is marked approved without a timestamp, or vice-versa."
        ],
        [
            "Business Logic Integrity",
            "CHECK (visibility='private' OR department_id IS NOT NULL)",
            "Enforced on <font name='Courier'>calendar_tasks</font>. Prevents orphan department events where visibility is 'department' but no department was assigned."
        ],
        [
            "Referential Integrity",
            "FOREIGN KEY ... ON DELETE RESTRICT (users on documents)<br/>ON DELETE SET NULL (logs/comments)<br/>ON DELETE CASCADE (versions/tags)",
            "Prevents orphaned rows. Prevents accidental destruction of company documents when removing users. Keeps audit logs even if users depart."
        ],
        [
            "Concurrency & Deduplication",
            "UNIQUE(document_id, version)<br/>UNIQUE(document_id, round, step_order)<br/>file_hash CHAR(64)",
            "Prevents duplicate version numbers during simultaneous uploads. Prevents duplicate approval steps in a round. Detects identical binary file uploads via SHA-256."
        ],
        [
            "Temporal Longevity",
            "DATETIME instead of TIMESTAMP",
            "TIMESTAMP in MySQL is signed 32-bit integer ending on January 19, 2038 (Y2038 bug). DATETIME supports dates from year 1000 to 9999."
        ]
    ]
    t_integ = Table([[Paragraph(c, styles['TableHead'] if i==0 else styles['TableCell']) for c in row] for i, row in enumerate(integ_data)], colWidths=[95, 145, 264])
    t_integ.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), c_primary),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, c_border),
        ('TOPPADDING', (0,0), (-1,-1), 4),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, c_bg_light]),
    ]))
    story.append(t_integ)
    story.append(Spacer(1, 14))
    
    # ---------------------------------------------------------
    # SECTION 5: DATA LIFECYCLE & EXECUTION FLOWS
    # ---------------------------------------------------------
    story.append(Paragraph("5. End-to-End Data Flows & Lifecycles", styles['SecHeading']))
    
    flows = [
        ("A. Document Upload & Ingestion Flow", [
            "1. User initiates upload. Application calculates SHA-256 hash of the binary file and queries <font name='Courier'>documents WHERE file_hash = ?</font> to check for duplicates.",
            "2. Inside an ACID Transaction: A new row is inserted into <font name='Courier'>documents</font> with status='draft', version=1, file_hash, and uploaded_by.",
            "3. Initial version row is inserted into <font name='Courier'>document_versions</font> (document_id, version=1, file_path, uploaded_by).",
            "4. <font name='Courier'>documents.current_version_id</font> is updated to point to the created version record.",
            "5. Tag tokens are split: existing tags are matched or inserted into <font name='Courier'>tags</font>, and pairs are bound in <font name='Courier'>document_tags</font>.",
            "6. An entry is appended to <font name='Courier'>activity_logs</font> with action_type='upload', capturing actor IP and snapshot name. Transaction commits."
        ]),
        ("B. Multi-Stage Approval & Resubmission Flow", [
            "1. Author clicks 'Submit for Review'. Document status transitions from 'draft' to 'pending_review'.",
            "2. The workflow engine looks up approval steps and inserts rows into <font name='Courier'>approval_workflows</font> with round=1, step_order=1..N, status='pending'.",
            "3. Step 1 approver receives an in-app alert via <font name='Courier'>notifications</font> (type='approval_request').",
            "4. Approver reviews and acts: If Approved, status becomes 'approved', acted_at=NOW(). If step 2 exists, step 2 becomes active. If final step passes, <font name='Courier'>documents.status</font> transitions to 'approved', and a reference number is generated via <font name='Courier'>document_reference_seq</font>.",
            "5. Rejection Branch: If an approver clicks 'Reject', their row gets status='rejected', remarks are saved, and <font name='Courier'>documents.status</font> becomes 'rejected'.",
            "6. Resubmission Round: When author modifies file and resubmits, the workflow queries <font name='Courier'>MAX(round) + 1</font>. New rows are inserted with round=2. The history of round 1 remains intact, preventing reviews from colliding or being overwritten."
        ]),
        ("C. Soft-Delete, Archival & Access Control Flow", [
            "1. Archival: Status becomes 'archived'. Document remains in storage for legal retention but is filtered out of regular active listings.",
            "2. Soft Delete: When a user deletes a file, <font name='Courier'>documents.deleted_at = NOW()</font> and <font name='Courier'>deleted_by = user_id</font> are set. No rows are removed from disk.",
            "3. Application Querying: Application queries the <font name='Courier'>active_documents</font> SQL View for operational lists, guaranteeing soft-deleted and archived files are automatically excluded without requiring manual WHERE clauses in every query.",
            "4. Access Verification: System checks if user is Admin (universal access), belongs to the document's department, or possesses a specific grant in <font name='Courier'>document_permissions</font> matching confidentiality classification."
        ])
    ]
    for flow_title, steps in flows:
        story.append(Paragraph(flow_title, styles['SubSecHeading']))
        for s in steps:
            story.append(Paragraph(s, styles['CustomBullet']))
            story.append(Spacer(1, 1))
        story.append(Spacer(1, 4))
    story.append(Spacer(1, 8))
    
    # ---------------------------------------------------------
    # SECTION 6: DEFENSE PANEL Q&A MASTERY
    # ---------------------------------------------------------
    story.append(PageBreak()) # Clean break to start defense section on fresh page
    story.append(Paragraph("6. Defense Panelist Q&A (Situational to Complex Technical)", styles['SecHeading']))
    story.append(Paragraph(
        "Below are 18 rigorous defense questions simulating an academic capstone or technical industry panel, structured across three difficulty tiers.",
        styles['CustomBody']
    ))
    
    qas = [
        # --- TIER 1: CONCEPTUAL & STRUCTURAL ---
        (
            "TIER 1: CONCEPTUAL & FOUNDATIONAL",
            "Q1: Why did you separate documents and document_versions into two different tables instead of having a single documents table with a version column?",
            "<b>Answer:</b> Storing revisions in a single table causes massive data redundancy and violates 2NF. Document-level metadata (such as category, department, permissions, creation date, and approval history) does not change every time a typo is corrected in a file. By splitting into a 1:N relationship, <font name='Courier'>documents</font> retains stable parent identity, while <font name='Courier'>document_versions</font> acts as an append-only ledger of physical files, sizes, hashes, and change notes. This also allows rollback to previous files without altering foreign key links in other tables."
        ),
        (
            "TIER 1: CONCEPTUAL & FOUNDATIONAL",
            "Q2: Why is the relationship between documents and tags Many-to-Many (M:N), and why did you create a junction table instead of storing comma-separated strings?",
            "<b>Answer:</b> A document can have multiple topical tags ('Finance', 'Confidential', 'Audit'), and a single tag applies to thousands of documents. Storing them as a comma-separated string in <font name='Courier'>documents.tags</font> violates First Normal Form (1NF) because values are non-atomic. It forces inefficient <font name='Courier'>LIKE '%tag%'</font> table scans that bypass indexes and make tag renaming or referential integrity impossible. The associative table <font name='Courier'>document_tags(document_id, tag_id)</font> uses a composite primary key, enabling index seeks, O(1) tag lookup, and instant cascading cleanup."
        ),
        (
            "TIER 1: CONCEPTUAL & FOUNDATIONAL",
            "Q3: What is the difference between your soft-delete mechanism and hard deletion, and why is hard deletion dangerous in this system?",
            "<b>Answer:</b> Hard deletion physically executes <font name='Courier'>DELETE FROM documents</font>, permanently purging the row and triggering cascading deletes on child versions and comments. In an enterprise DMS, this destroys audit trails, breaks legal compliance, and makes accidental loss unrecoverable. Our soft-delete sets <font name='Courier'>deleted_at = DATETIME</font> and <font name='Courier'>deleted_by = user_id</font>. The data remains intact, can be restored instantly, and is safely hidden from active operational workflows using our <font name='Courier'>active_documents</font> view."
        ),
        (
            "TIER 1: CONCEPTUAL & FOUNDATIONAL",
            "Q4: Why does the categories table have a color column with a CHECK constraint?",
            "<b>Answer:</b> The UI renders visual category badges. Without schema-level constraints, a malformed string or XSS vector could be injected into <font name='Courier'>categories.color</font>. We enforce domain integrity using <font name='Courier'>CHECK (color REGEXP '^#[0-9a-fA-F]{6}$')</font>, guaranteeing that only valid 6-character hexadecimal color codes (e.g. #3B82F6) can ever be stored, shielding the frontend renderer from injection or style breakage."
        ),
        (
            "TIER 1: CONCEPTUAL & FOUNDATIONAL",
            "Q5: Why did you use DATETIME instead of TIMESTAMP across all tables in your hardened schema?",
            "<b>Answer:</b> MySQL's native <font name='Courier'>TIMESTAMP</font> datatype is stored as a 32-bit integer representing seconds since the Unix epoch (1970-01-01). On January 19, 2038, 32-bit timestamps will overflow (the Year 2038 problem), causing dates to flip to 1901 and crashing system logic. <font name='Courier'>DATETIME</font> uses an 8-byte/5-byte packed format storing values up to year 9999, completely immunizing the document archive against future timestamp overflow."
        ),

        # --- TIER 2: SITUATIONAL & WORKFLOW EDGE CASES ---
        (
            "TIER 2: SITUATIONAL & BUSINESS LOGIC",
            "Q6: What happens if an employee who authored 500 documents resigns and their user account is deleted?",
            "<b>Answer:</b> In our hardened schema, the foreign key on <font name='Courier'>documents.uploaded_by</font> is defined as <font name='Courier'>ON DELETE RESTRICT</font>. The database engine will literally refuse to drop the user record with an integrity violation error. Instead of hard-deleting staff, our system enforces soft-deactivation: <font name='Courier'>users.is_active = 0</font> and <font name='Courier'>users.deleted_at = NOW()</font>. This ensures that all historical documents, authorship logs, and accountability records remain 100% intact and linked."
        ),
        (
            "TIER 2: SITUATIONAL & BUSINESS LOGIC",
            "Q7: A document is rejected by an approver, revised by the author, and resubmitted. How does your schema prevent old approval comments from mixing with new ones?",
            "<b>Answer:</b> In naive schemas, re-submitting overwrites the existing workflow rows or appends rows with duplicate step numbers, causing confusion. We solved this by adding an explicit <font name='Courier'>round INT NOT NULL DEFAULT 1</font> column and a unique constraint: <font name='Courier'>UNIQUE(document_id, round, step_order)</font>. When a document is rejected in Round 1 and resubmitted, new workflow steps are created with <font name='Courier'>round = 2</font>. Round 1 remains frozen for historical audit, while Round 2 tracks the live resubmission pipeline."
        ),
        (
            "TIER 2: SITUATIONAL & BUSINESS LOGIC",
            "Q8: How does your database prevent a user from approving their own document (self-approval)?",
            "<b>Answer:</b> Self-approval violates separation of duties. While the application service layer validates that <font name='Courier'>approver_id != documents.uploaded_by</font> prior to inserting workflow rows, the database enforces status integrity using: <font name='Courier'>CHECK ((status='pending' AND acted_at IS NULL) OR (status<>'pending' AND acted_at IS NOT NULL))</font>. Additionally, an approver cannot exist as an active approver on an unauthorized step due to step-order constraints."
        ),
        (
            "TIER 2: SITUATIONAL & BUSINESS LOGIC",
            "Q9: What prevents two users from uploading Revision 2 of the exact same document at the exact same millisecond?",
            "<b>Answer:</b> We enforce concurrency control at the storage engine level with the constraint: <font name='Courier'>UNIQUE KEY uq_doc_version (document_id, version)</font> on <font name='Courier'>document_versions</font>. If two concurrent HTTP requests attempt to write version 2 for document ID 45 simultaneously, MySQL's InnoDB row-level locking ensures the first transaction writes successfully while the second immediately fails with an integrity violation error (1062 Duplicate entry), completely preventing duplicate revision numbers."
        ),
        (
            "TIER 2: SITUATIONAL & BUSINESS LOGIC",
            "Q10: Why do you have both role-based access control (RBAC) in users and an explicit document_permissions table?",
            "<b>Answer:</b> RBAC (<font name='Courier'>admin</font>, <font name='Courier'>department_head</font>, <font name='Courier'>staff</font>) handles broad organizational governance: department heads see department documents, staff see their own work. However, enterprise workflows have cross-functional exceptions: an HR policy might need to be reviewed by a specific Finance auditor, or a highly sensitive executive contract must be hidden from normal department staff. <font name='Courier'>document_permissions</font> provides a granular Access Control List (ACL) supporting read/comment/edit overrides for specific users or external departments without elevating their overall system role."
        ),
        (
            "TIER 2: SITUATIONAL & BUSINESS LOGIC",
            "Q11: Suppose two users approve two documents at the exact same moment. How do you prevent duplicate document reference numbers (e.g., REF-2026-0001)?",
            "<b>Answer:</b> Naive systems execute <font name='Courier'>SELECT MAX(id) FROM documents</font>, which suffers from race conditions where two threads read the same max value and generate duplicate numbers. We created the dedicated atomic counter table <font name='Courier'>document_reference_seq (prefix, year, next_val)</font>. Inside a database transaction, we execute an atomic update: <font name='Courier'>UPDATE document_reference_seq SET next_val = LAST_INSERT_ID(next_val + 1) WHERE prefix = ? AND year = ?</font>. This utilizes InnoDB's internal sequence locking to guarantee that each transaction receives a strictly sequential, non-colliding integer."
        ),

        # --- TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION ---
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q12: In activity_logs, you store user_name_snapshot which duplicates the user's name already in users. Doesn't this violate Third Normal Form (3NF)? How do you defend this?",
            "<b>Answer:</b> Yes, strictly speaking, it violates 3NF by introducing transitive redundancy. However, this is an intentional, industry-standard pattern called <b>Audit Durability Denormalization</b>. In an enterprise audit log, immutability takes precedence over pure normalization. If an employee changes their name, or if their account is anonymized under privacy laws (like GDPR) or purged, querying an audit trail using an inner join on <font name='Courier'>users.id</font> would either return NULL or show the altered name retrospectively. Storing a snapshot guarantees an unalterable historical record of who executed the transaction at that exact moment in time."
        ),
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q13: Notice that documents has both parent_document_id and current_version_id, while document_versions points back to documents. Isn't this circular and redundant?",
            "<b>Answer:</b> They serve two completely distinct architectural purposes: (1) <font name='Courier'>parent_document_id</font> is a self-referencing relationship representing <i>cloning or branching</i> (e.g. 'Document B was created as a copy of Template A'). It is NOT used for revisioning. (2) <font name='Courier'>current_version_id</font> is a 1:1 optimization pointer referencing the active production record in <font name='Courier'>document_versions</font>. Without this pointer, every single query searching for a document's current file path, size, or hash would require an expensive correlated subquery (<font name='Courier'>WHERE version = (SELECT MAX(version)...)</font>). Having an explicit pointer allows instant O(1) joins while preserving the 1:N revision history."
        ),
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q14: Explain the circular dependency between departments and users. How did you resolve it during schema generation?",
            "<b>Answer:</b> <font name='Courier'>users</font> references <font name='Courier'>departments(id)</font> to assign employees to an organization, while <font name='Courier'>departments</font> references <font name='Courier'>users(id)</font> via <font name='Courier'>head_user_id</font> to designate who manages that department. If both foreign keys were declared in their initial <font name='Courier'>CREATE TABLE</font> statements, neither table could be created first without throwing a foreign key constraint failure. We resolved this cleanly by creating <font name='Courier'>departments</font> with <font name='Courier'>head_user_id INT NULL</font> first, then creating <font name='Courier'>users</font> with its FK to <font name='Courier'>departments</font>, and finally executing an <font name='Courier'>ALTER TABLE departments ADD CONSTRAINT fk_dept_head FOREIGN KEY...</font>. This breaks the cycle cleanly during migrations."
        ),
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q15: How does your database handle ACID properties during a file upload?",
            "<b>Answer:</b> A file upload spans multiple entities (<font name='Courier'>documents</font>, <font name='Courier'>document_versions</font>, <font name='Courier'>document_tags</font>, and <font name='Courier'>activity_logs</font>). We wrap this workflow in a single database transaction: (1) <b>Atomicity:</b> If tag insertion fails or the version pointer fails, the entire transaction rolls back; no orphaned document record is left behind. (2) <b>Consistency:</b> Foreign keys ensure no version exists without a valid document or user. (3) <b>Isolation:</b> Under InnoDB's default REPEATABLE READ isolation level, concurrent transactions cannot read partial uncommitted version rows. (4) <b>Durability:</b> Once committed, the InnoDB redo log ensures writes survive power failures or crashes."
        ),
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q16: Why did you add file_hash CHAR(64) to both documents and document_versions?",
            "<b>Answer:</b> <font name='Courier'>file_hash</font> stores the cryptographic SHA-256 hash (64 hexadecimal characters) of the file payload. It fulfills two enterprise needs: (1) <b>Data Deduplication:</b> If a user attempts to upload a 50MB PDF that already exists in the system under a different filename, the system compares hashes and reuses physical storage instead of duplicating disk space. (2) <b>Integrity Verification:</b> It prevents silent file corruption (bit rot) or unauthorized direct filesystem tampering. Periodically or upon download, the file hash can be re-computed and compared to the database hash."
        ),
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q17: What indexing strategy did you employ in this database, and how do you prevent full table scans when millions of documents exist?",
            "<b>Answer:</b> We implemented targeted composite and covering indexes based on real query access patterns: (1) <font name='Courier'>idx_documents_dept_stat(department_id, status)</font> satisfies dashboard filtering without table scans. (2) <font name='Courier'>idx_notif_user_read(user_id, is_read)</font> powers the high-frequency unread notification badge query instantly. (3) <font name='Courier'>idx_al_doc_created(document_id, created_at)</font> renders audit logs in reverse chronological order via index scan. (4) <font name='Courier'>FULLTEXT INDEX idx_documents_ft(title, description)</font> allows tokenized full-text keyword searches using boolean mode instead of disastrous <font name='Courier'>LIKE '%keyword%'</font> scans that cripple production engines."
        ),
        (
            "TIER 3: COMPLEX TECHNICAL & DEFENSE INQUISITION",
            "Q18: If your system scales to 10 million documents and 50 million versions, what database architecture or partitioning would you recommend next?",
            "<b>Answer:</b> To scale beyond single-instance InnoDB limits: (1) <b>Horizontal Table Partitioning:</b> Partition <font name='Courier'>activity_logs</font> and <font name='Courier'>login_attempts</font> by RANGE on <font name='Courier'>created_at</font> (e.g. yearly or monthly partitions) so old historical partitions can be archived or dropped with zero table lock. (2) <b>Read-Replica Architecture:</b> Route write transactions (<font name='Courier'>INSERT/UPDATE</font>) to an active Master database and delegate heavy analytical queries and search reads to read-replicas. (3) <b>Blob Offloading:</b> Ensure physical document binaries reside in Object Storage (e.g. MinIO or AWS S3), keeping the MySQL database lean and storing only metadata, hashes, and storage paths."
        )
    ]
    
    current_tier = ""
    for tier, q, ans in qas:
        if tier != current_tier:
            current_tier = tier
            story.append(Spacer(1, 6))
            story.append(Paragraph(f"<b>{current_tier}</b>", styles['SubSecHeading']))
            story.append(HRFlowable(width="100%", thickness=1, color=c_blue, spaceBefore=2, spaceAfter=6))
        
        q_item = [
            Paragraph(q, styles['QTitle']),
            Paragraph(ans, styles['QAnswer']),
            Spacer(1, 3)
        ]
        story.append(KeepTogether(q_item))

    # Build the document
    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"Successfully generated PDF: {filename}")

if __name__ == "__main__":
    out_path = sys.argv[1] if len(sys.argv) > 1 else "/Applications/XAMPP/xamppfiles/htdocs/Trackify/Trackify_Database_Defense_Guide.pdf"
    build_pdf(out_path)
