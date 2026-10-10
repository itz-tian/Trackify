# Role test checklist (v19)

All seed accounts use the password `password`:
admin `admin@dms.local` · dept heads `hr.head@dms.local`, `finance.head@dms.local`, `it.head@dms.local`, `operations.head@dms.local` · staff `staff1@dms.local` - `staff4@dms.local`.

Log in as each role in its own browser profile / private window and tick the rows. The expected results come from reading the code (it could not be run when this list was written), so report anything that behaves differently.

| # | Flow | Admin | Dept head | Staff | Viewer |
|---|------|:-----:|:---------:|:-----:|:------:|
| 1 | Log in, dashboard loads, charts render | ☐ | ☐ | ☐ | ☐ |
| 2 | Sidebar shows the right sections (Admin section admin-only, Approvals hidden from viewer) | ☐ | ☐ | ☐ | ☐ |
| 3 | Upload a document (viewer must be blocked) | ☐ | ☐ | ☐ | ☐ |
| 4 | Submit a draft for approval (confirm modal appears) | ☐ | ☐ | ☐ | – |
| 5 | Approve / reject a document from another user | ☐ | ☐ (own dept only) | should be refused | – |
| 6 | Archive a document, then restore it (modals) | ☐ | ☐ | ☐ | – |
| 7 | Permanently delete from Archive | allowed | refused | refused | – |
| 8 | Open a document from ANOTHER department by changing `?id=` in the URL | allowed | must 404 | must 404 | must 404 unless approved |
| 9 | Download/preview a file; paste its `/uploads/...` path directly in the address bar (must be blocked) | ☐ | ☐ | ☐ | ☐ |
| 10 | Calendar: add / edit / done / delete a task, share with department | ☐ | ☐ | ☐ | private only |
| 11 | Activity log: admin sees all, dept head only own department, others only themselves | ☐ | ☐ | ☐ | ☐ |
| 12 | Admin pages (`admin/users.php`, departments, categories) open by URL | allowed | refused | refused | refused |
| 13 | Reports page | allowed | allowed | refused | refused |
| 14 | 5 wrong passwords → lockout message; “attempts left” hint appears from the 3rd | ☐ | | | |
| 15 | Leave the page idle: “Still there?” modal appears before sign-out; “Stay signed in” extends | ☐ | | | |
| 16 | Visit a non-existent id (`document_view.php?id=99999`) and `/ITP104-System/nope` → friendly 404 | ☐ | | | |
