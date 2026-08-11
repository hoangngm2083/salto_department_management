# Project Management Plan — bản chốt (đã điều chỉnh so với new_business.md)

Đây là bản kết hợp giữa `new_business.md` (bản sketch gốc) với cấu trúc/convention thực tế của project hiện tại. Các quyết định dưới đây đã được thảo luận và chốt qua nhiều vòng — coi đây là nguồn tham chiếu chính khi bắt đầu code từng phase, không cần đọc lại toàn bộ lịch sử chat.

---

## 1. Kiến trúc & convention — những gì KHÔNG làm theo new_business.md

| Đề xuất trong new_business.md | Quyết định | Lý do |
|---|---|---|
| `app/Modules/{Identity,Organization,...}/{Application,Domain,Infrastructure,Presentation}` | **Không làm** | Overhead thuần cho quy mô hiện tại (1 dev, 3 role cố định). Vi phạm CLAUDE.md ("don't create new base folders without approval"). Thay bằng: mở rộng subfolder theo domain trong các base folder đã có sẵn (`app/Services/Import/` đã là tiền lệ) — xem mục 1.1. |
| Bảng `permissions`, `role_user`, `permission_role` | **Không làm** | Chỉ có 3 role cố định (admin/manager/employee), không có nhu cầu role động. Giữ nguyên Sanctum ability-string (`AuthService::abilitiesFor()`) + Policy pattern hiện có, chỉ thêm ability mới khi cần (`projects:read`, `approvals:approve`...). |
| `departments.manager_employee_id` | **Không thêm** | `Department::managers()` (hasMany theo `position=manager`) đã giải quyết được bài toán này, đổi sang 1 FK sẽ là breaking change semantics. |
| Đổi tên `position` | **Không đổi** | `position` đã đúng nghĩa system role (employee/manager/admin), không lẫn với job title — không cần đổi, tránh churn 41 file phụ thuộc. |
| WebSocket notification (Phase 5) | **Hoãn** | Cần thêm dependency mới (Reverb/Pusher) — phải xin approval riêng theo CLAUDE.md. Dùng database notification (đã có sẵn) trước. |
| Redis queue / Horizon (Phase 9) | **Hoãn, để cuối, optional** | Không cần thiết cho portfolio/demo scope, ưu tiên depth chức năng hơn ops polish. |

### 1.1. Cấu trúc thư mục thực tế sẽ dùng

Không tạo `Modules/`. Mở rộng đúng convention hiện có (domain subfolder trong base folder đã tồn tại):

```
app/Services/Approval/{Workflows,Handlers}/...   (giống app/Services/Import/)
app/Services/Assignment/...
app/Http/Controllers/Api/V1/Project*.php, Task*.php, Approval*.php
app/Http/Requests/{Project,Assignment,Approval,Task}/...
app/Http/Resources/{Project,Assignment,Approval,Task}/...
app/Policies/Project*Policy.php, Task*Policy.php, ApprovalRequestPolicy.php
app/Enums/...   (mọi status mới đều dùng PHP backed enum, không dùng DB enum() thô)
```

### 1.2. Enum convention

Mọi bảng mới dùng **PHP backed enum + cast**, theo kiểu `LeaveRequestStatus`/`NotificationReadStatus` (không theo kiểu DB `enum()` thô của `Department.status` — kiểu cũ hơn, khó ALTER khi đổi giá trị). `ActiveStatus` (Active/Inactive) dùng chung cho `Level` và `ProjectRole` để tránh 2 enum trùng lặp.

### 1.3. Frontend convention

Không thêm dependency mới (TanStack Query, React Hook Form, Zod...) cho các trang Project/Task/Approval — giữ nguyên pattern hiện tại của `fe/vite-project`: axios + `useCursorList` hook tự viết + state cục bộ. Thêm lib mới cần xin approval theo CLAUDE.md, và pattern cũ đã chạy ổn qua Employees/Departments/LeaveRequests.

---

## 2. Module Organization (mở rộng bảng hiện có + 1 bảng mới)

### `employees` — thêm cột (không đổi/xoá cột nào đang có)

```php
current_level_id      // nullable FK → levels, nullOnDelete
manager_employee_id   // nullable, self-FK → employees, nullOnDelete
status                // backed enum: active | inactive | resigned, default 'active'
```

- Bỏ `employee_code` — YAGNI, `email` đã là natural key đủ dùng.
- Quan hệ mới trên model: `Employee::manager(): BelongsTo` (self), `Employee::directReports(): HasMany` (self).
- Chỉ `admin` được set/đổi `manager_employee_id` và `current_level_id` (mở rộng `ALLOWED_UPDATE_FIELDS_BY_ROLE` trong `EmployeeService`).
- Validate: `manager_employee_id !== id`.
- Dùng cho: approver `DIRECT_MANAGER` ở Level Promotion (Phase F) — ưu tiên `employee.manager_employee_id`, fallback department-manager-pool nếu null.

**Rule nghỉ việc/xoá:** `SoftDelete` dùng cho data-entry sai (xoá hẳn, không có lịch sử thật cần giữ). `status → resigned` dùng khi nhân viên đã nghỉ nhưng cần giữ lịch sử (project_assignments, tasks, approval records cũ vẫn query được). Khi chuyển sang `resigned`, hệ thống tự động đóng (`end_date = now()`) mọi `project_assignment` và `assignment_role_period` đang active của người đó — không cascade xoá gì. Cả soft-delete lẫn transition sang `resigned` đều phải pass qua `ProjectManagerGuard` (xem mục 3.1) trước khi thực hiện, phòng trường hợp người này đang là PM duy nhất của 1 project nào đó.

### `levels` (mới)

```php
id, name, slug (unique), rank (unsigned smallint, unique),
probation_salary_percentage (unsigned tinyint nullable, 0-100),
status (ActiveStatus), timestamps, softDeletes
```

### `departments` — không đổi gì.

---

## 3. Module Project Assignment (hoàn toàn mới)

```php
projects
├── id, name, slug (unique), description (nullable)
├── status (backed enum ProjectStatus: Planned|Active|Completed|Cancelled)
├── start_date, end_date (nullable)
├── timestamps, softDeletes

project_roles
├── id, name, slug (unique), description (nullable)
├── status (ActiveStatus — dùng chung với levels)
├── timestamps, softDeletes

project_managers
├── id, project_id, employee_id, start_date, end_date (nullable)
├── timestamps (created_at + updated_at)

project_assignments
├── id, employee_id, project_id, start_date, end_date (nullable)
├── status (backed enum: Pending|Active|Ended|Cancelled)
├── created_by, timestamps
// Tạo trực tiếp bởi admin (Phase C) → status = Active ngay, chưa qua approval.
// Chỉ luồng request (Phase G) mới đi Pending → approval → Active.

assignment_role_periods
├── id, project_assignment_id, project_role_id
├── start_date, end_date (nullable)
├── source_approval_request_id (nullable FK → approval_requests)
├── timestamps
```

**Ràng buộc kỹ thuật quan trọng:** project chạy **MySQL** — không thể tạo partial/filtered unique index kiểu Postgres cho "1 assignment/role active tại 1 thời điểm". Bắt buộc `lockForUpdate()` + transaction ở tầng service, làm ngay từ Phase C, không để dồn qua Approval mới xử lý.

### 3.1. Ràng buộc: project luôn phải có PM

Invariant: 1 project luôn có **≥ 1 `project_managers` active** tại mọi thời điểm, từ lúc tạo tới khi kết thúc.

- `POST /api/projects` bắt buộc kèm ít nhất 1 `manager_employee_id` ngay khi tạo — không cho tạo project rồi gán PM sau.
- Bất kỳ hành động nào có thể làm project về 0 PM active đều bị chặn (422), **trừ khi** có PM thay thế được chỉ định cùng lúc: soft-delete employee, employee chuyển `status → resigned`, hoặc gỡ PM trực tiếp khỏi project. Chỉ chặn khi về đúng 0 PM — nếu project còn PM khác thì gỡ bình thường, không bắt buộc chọn thay thế.

**Vấn đề kiến trúc:** việc chặn soft-delete/nghỉ việc (hành động ở `EmployeeService`, thuộc Organization) phải kiểm tra dữ liệu `project_managers` (thuộc Project Assignment) — nếu `EmployeeService` query thẳng bảng đó sẽ đảo ngược chiều dependency đã định (`Organization ← Project Assignment`). Xử lý bằng contract, đúng nguyên tắc đã áp dụng cho Approval↔Assignment (không query chéo bảng nội bộ module khác):

```php
// Định nghĩa ở Organization, nơi EmployeeService cần dùng
interface ProjectManagerGuard
{
    /** @return list<string> tên project sẽ mất PM cuối cùng nếu gỡ $employee khỏi vai trò PM */
    public function projectsLeftWithoutManagerIfRemoved(Employee $employee): array;
}
// Implement trong Project Assignment, inject vào EmployeeService
```

`EmployeeService::delete()` và transition `status → resigned` gọi guard này trước, throw exception (422, kèm tên các project) nếu danh sách không rỗng.

**API gỡ/thay PM trực tiếp:**

```
POST   /api/projects/{project}/managers                          (thêm PM — luôn cho phép)
DELETE /api/projects/{project}/managers/{projectManager}
         body (optional): { replacement_employee_id }
         // là PM active cuối cùng → bắt buộc replacement_employee_id, tạo PM mới + end PM cũ trong 1 transaction
         // còn PM khác → gỡ bình thường, không cần replacement
```

---

## 4. Approval Engine — Tier 1 (đa bước, cho thay đổi tổ chức/nhân sự)

Giữ nguyên thiết kế `new_business.md` mục 14-27, chỉ 2 điều chỉnh:

- Dùng `$table->morphs('requestable')` thay vì tự khai `requestable_type`/`requestable_id`.
- Mọi status/action dùng backed enum (`ApprovalStatus`, `ApprovalStepStatus`, `ApprovalActionType`).

```
approval_requests, approval_steps, approval_actions   (generic engine)
project_assignment_requests (+ _roles)
project_transfer_requests (+ _roles)
role_change_requests        (change_mode: ADD|REPLACE|REMOVE)
level_promotion_requests    (2 bước: manager → admin)
```

4 workflow MVP giữ nguyên như bản gốc. `created_by` trên các bảng request này là cần thiết thật sự (lần đầu tiên requester ≠ subject employee — manager có thể request thay nhân viên). Locking `lockForUpdate()` khi approve/apply — dành riêng cho tier này (đa bước, tần suất thấp, rủi ro cao hơn).

**Không gộp các bảng detail này lại với nhau** — mỗi workflow type vẫn có bảng riêng như thiết kế gốc, vì data shape khác nhau đủ nhiều (role_change có `change_mode`/`from_role_id`/`to_role_id`, transfer có `source`/`target`...).

---

## 5. Module Task Management (mới, quyết định riêng — KHÔNG dùng Approval Engine)

Lý do: task xảy ra hàng ngày, tần suất cao, rủi ro thấp hơn nhiều so với promotion/transfer → dùng flat-status pattern kiểu `LeaveRequest`, không cần Workflow/Registry/State machine của Tier 1.

```php
tasks
├── id, project_id
├── assigned_to      (nullable FK → employees; validate: phải có project_assignment active trên project này)
├── created_by        (FK → employees, reporter)
├── reviewed_by        (nullable FK → employees)
├── title, description (nullable)
├── status              (backed enum TaskStatus: todo|in_progress|in_review|done|cancelled — snake_case values)
├── due_date, reviewed_at (nullable), review_note (nullable)
├── timestamps, softDeletes
```

**Workflow:** `Todo → InProgress` (assignee tự chuyển) → `InReview` (assignee submit) → `Done` (PM approve) hoặc → `InProgress` (PM reject + `review_note`). Mọi trạng thái trừ `Done` → `Cancelled` (PM/admin).

**Authorization** (theo khuôn `LeaveRequestPolicy`): tạo/assign = PM của project hoặc admin; `Todo→InProgress→InReview` = chỉ assignee; `InReview→Done`/reject = chỉ PM của project hoặc admin (không tự duyệt task của mình).

```php
task_comments
├── id, task_id, employee_id, body
├── task_status   // nullable backed enum TaskStatus — set khi comment đi kèm 1 lần chuyển trạng thái, null nếu comment thường
├── created_at    // không có update/delete ở bản đầu (create + list only)
```

Bảng này vừa là comment tự do, vừa đóng vai trò "trace" hiển thị dạng `"{employee} đã cập nhật trạng thái thành {task_status}: {body}"` — thay cho việc append text vào `description` (đã loại bỏ vì rủi ro race condition, mất khả năng query, mất tính audit khi sửa được 1 cột text).

```php
task_delay_requests   // đứng riêng, CHƯA gộp (xem mục 6)
├── id, task_id, requested_by
├── current_due_date, requested_due_date   // snapshot due_date hiện tại lúc submit, chống stale/tamper
├── reason
├── status         (backed enum: Pending|Approved|Rejected|Cancelled)
├── reviewed_by, reviewed_at, review_note (nullable)
├── timestamps
```

Rule: task chưa `Done`/`Cancelled` mới được request delay; không có 2 pending delay-request cùng lúc cho 1 task; approve → set thẳng `tasks.due_date` ngay (không cần phân biệt Approved/Applied vì áp dụng tức thì, không có effective_date tương lai).

### API (RESTful, snake_case, query-param filter thay vì pseudo-path)

```
GET/POST  /api/projects/{project}/tasks?status=in_review&assigned_to=
GET/PATCH /api/tasks/{task}                              (shallow)
GET       /api/employees/{employee}/tasks                 ("my tasks")
GET/POST  /api/tasks/{task}/comments
GET/POST  /api/task-delay-requests?task_id=&status=       (flat top-level, giống hệt leave-requests)
PATCH     /api/task-delay-requests/{taskDelayRequest}      (body: status + review_note)
```

`GET /api/projects/{project}` trả kèm `done_count`/`total_count`/`overdue_count` (progress) — không tách endpoint `/progress` riêng. Không có `/tasks/board` — FE tự group theo status từ response index thường.

### 5.1. Project member search (dùng khi assign task)

```
GET /api/projects/{project}/members?name=&project_role_id=&per_page=10
```

Dùng chung 1 endpoint cho cả tab "Members" ở trang chi tiết project (list đầy đủ) và autocomplete khi assign task — chỉ khác `per_page`. Cursor pagination như mọi list khác trong app, không có cách phân trang riêng cho search.

```php
Employee::query()
    ->whereHas('projectAssignments', fn ($q) => $q->where('project_id', $project->id)->where('status', 'active'))
    ->where('status', 'active')
    ->when($data['name'] ?? null, fn ($q, $name) => $q->nameContains($name))
    ->when($data['project_role_id'] ?? null, fn ($q, $roleId) => $q->whereHas(
        'projectAssignments', fn ($qq) => $qq->where('project_id', $project->id)
            ->whereHas('activeRolePeriods', fn ($qqq) => $qqq->where('project_role_id', $roleId))
    ))
```

Filter theo tên dùng **contains search**, tái dùng scope có sẵn `Employee::nameContains()` (app/Models/Employee.php) — **không** viết lại `where('name', 'like', "%{$name}%")` thủ công, vì scope này đã escape ký tự đặc biệt của LIKE (`%`, `_`) bằng escape character riêng (`!`, không dùng backslash vì MySQL/SQLite xử lý khác nhau) và đã có test bảo vệ hành vi này. Việc contains-search này không dùng được index btree trên `employees.name` (khác prefix search của `GET /api/employees`) — chấp nhận được vì phạm vi tìm kiếm chỉ trong member của 1 project (vài chục người), không cần fulltext index. Không filter theo level.

Response:
```json
{ "data": [{ "id": 15, "name": "Nguyễn Văn Hoàng", "level": "Senior", "roles": ["Backend", "Tech Lead"] }] }
```

FE hiển thị: **{name}** (đậm) / `[level, ...roles].filter(Boolean).join(', ')` (nhạt) — vd "Senior, Backend, Tech Lead". `filter(Boolean)` để tránh dấu phẩy thừa khi `level` null (employee chưa được gán `current_level_id`).

**2 component search riêng biệt, dùng chung phần hiển thị:**
- "Add employee vào project" → search toàn bộ employee, dùng `GET /api/employees?name=&department_id=` (prefix search, đã có sẵn).
- "Assign employee vào task" → search trong member của project, dùng `GET /api/projects/{project}/members?name=&project_role_id=` (contains search, mục này).

Authorization: PM của project (qua `project_managers`) hoặc admin.

---

## 6. Backlog — refactor sau (chưa làm ngay)

**Gộp `leave_requests` + `task_delay_requests` vào 2 bảng dùng chung (chỉ tier-2 — request 1-bước-duyệt):**

```php
requests                  // generic tracking, dùng chung cho mọi loại request 1-bước
├── id, requested_by, reason, status
├── reviewed_by, reviewed_at, review_note
├── version                // optimistic lock (thay cho lockForUpdate — tier-2 tần suất đụng độ thấp)
├── timestamps

request_details            // 1 bảng chung, cột nullable theo type, index request_id
├── id, request_id (FK → requests, indexed)
├── employee_id, start_date, end_date            (nullable — dùng cho leave)
├── task_id, current_due_date, requested_due_date (nullable — dùng cho task_delay)
```

**Chưa làm ngay vì:** `leave_requests` là tính năng đã ship + đã có 4 test file (`LeaveRequestCrudTest`, `LeaveRequestAuthorizationTest`, `LeaveRequestNotificationTest`, `SendLeaveRequestRemindersTest`) + Event/Listener/Notification riêng. Refactor này sẽ động vào Model/Policy/Service/Controller/Resource/migration + toàn bộ test hiện có — cần làm thành 1 đợt riêng khi hệ thống đã ổn định, không bundle chung với việc thêm Task Management.

Lưu ý: quyết định gộp này **chỉ áp dụng tier-2**, không áp dụng cho Tier-1 Approval Engine (mục 4) — 2 tầng độc lập nhau.

---

## 7. Thứ tự triển khai & ước lượng

| Phase | Nội dung | Ước lượng |
|---|---|---|
| A ✅ (2026-08-05) | `levels` CRUD + field mới trên `employees` (status, current_level_id, manager_employee_id) | 2-3 ngày |
| B ✅ (2026-08-05) | `projects`, `project_roles`, `project_managers` CRUD + Policy | 3-4 ngày |
| C ✅ (2026-08-10) | `project_assignments` + `assignment_role_periods` (tạo trực tiếp bởi admin), work-history endpoint, lock+validate active period | 4-6 ngày |
| **C.5 ✅ (2026-08-10)** | **Task Management** (Task + Comment + Delay Request + Policy + progress trên Project resource, cộng FE Kanban board + modal) | **6-8 ngày** |
| D | Approval Engine core (requests/steps/actions, Workflow interface, Registry, State machine, approve/reject/cancel, locking) | 6-9 ngày |
| E | Role Change workflow end-to-end (ADD/REPLACE/REMOVE) — **milestone demo đầu tiên** | 3-4 ngày |
| F | Level Promotion (2 bước manager→admin, scheduler effective_date) | 3-4 ngày |
| G | Project Assignment Request + Project Transfer | 6-8 ngày |
| H (tuỳ chọn) | WebSocket realtime — cần approval dependency mới | 2-3 ngày |
| I (tuỳ chọn, cuối) | Redis queue/Horizon, rate limit, OpenAPI polish | 3-5 ngày |
| Backlog | Gộp `requests`/`request_details` (mục 6) | chưa lên lịch |

**Tổng phần bắt buộc (A → G, đã gồm Task Management): ~33-46 ngày công.**

Task Management (C.5) chèn ngay sau Assignment Core, **trước** Approval Engine (D) — vì không phụ thuộc gì vào engine đó, giúp có sản phẩm demo được (dashboard + kanban) sớm hơn.

### 7.1. Ghi chú triển khai Phase A (đã xong 2026-08-05)

Điểm mục 2 chưa nói rõ, đã hỏi lại user và chốt: **`employees.status` cho phép admin + manager set/đổi** (manager giới hạn trong phạm vi department mình, theo đúng scope hiện có của `EmployeePolicy::update`) — khác với `current_level_id`/`manager_employee_id` là admin-only. Đã cập nhật `EmployeeService::ALLOWED_UPDATE_FIELDS_BY_ROLE` theo quyết định này.

`LevelPolicy` được thiết kế mirror `DepartmentPolicy` (admin bypass qua `before()`, mutate admin-only) nhưng **không** copy y nguyên phần scoping của Department: `levels` không có khái niệm "sở hữu" theo department nên `viewAny`/`view` cho manager trả `true` không điều kiện (không giống Department's `viewAny` luôn `false`). Ability `levels:read` chỉ cấp cho admin+manager (không cấp cho employee), mirror đúng theo `departments:read`.

Đã sửa 1 bug thời điểm này gây ra bởi cột `employees.status` mới: `SendLeaveRequestReminders::pendingReminderQuery()` join `employees`+`leave_requests` và trước đó dùng `where('status', ...)` không qualify — giờ ambiguous vì cả 2 bảng đều có `status`. Đã qualify thành `leave_requests.status`/`reminder_sent_at`/`start_date`.

Cả `Employee` và `Level` model được thêm `protected $attributes = ['status' => 'active']` (Eloquent default attribute values) để instance chưa persist (`factory()->make()`, `new Model()`) có `status` non-null ngay từ đầu — nếu không, enum cast (`$this->status->value` trong Resource) crash trên các model chưa qua DB (DB column default không tự phản ánh vào model in-memory).

### 7.2. Ghi chú triển khai Phase B (đã xong 2026-08-05)

**Cấu trúc file thực tế** (đúng mục 1.1, không tạo `app/Contracts/` vì CLAUDE.md cấm tạo base folder mới không xin phép): `ProjectManagerGuard` (interface) + `ProjectManagerGuardService` (implementation) đặt chung phẳng trong `app/Services/`, bind trong `AppServiceProvider::register()`. `project_managers` không có cột `status`/`deleted_at` — "gỡ PM" nghĩa là set `end_date = now()` trên record đang active (temporal, giống `assignment_role_periods` sau này), không xoá/soft-delete gì; route vẫn dùng `DELETE` theo đúng mục 3.1 dù bản chất là update.

**`POST /api/projects` bắt buộc `manager_employee_ids` (mảng, tối thiểu 1)** — không phải field số ít như câu chữ "ít nhất 1" trong mục 3.1 có thể gợi ý, vì `project_managers` vốn là quan hệ nhiều-PM. Tạo project + PM đầu tiên trong 1 transaction (`ProjectService::upsert`), validate mỗi id phải là employee `status=active` (`Rule::exists(...)->where('status','active')`).

**Khoá đồng thời khi gỡ PM** (`ProjectManagerService::remove`): đếm active-manager với `lockForUpdate()` trước khi quyết định có bắt buộc `replacement_employee_id` hay không — cùng tinh thần locking mục 3 yêu cầu cho `project_assignments`, áp dụng sớm ở đây vì rủi ro race tương tự (2 request gỡ PM đồng thời có thể cùng đọc "còn 2 PM" và cùng cho qua).

**Quyết định Policy không hỏi lại (suy ra từ new_business.md 5.2 + convention có sẵn):**
- `ProjectPolicy`/`ProjectRolePolicy` mirror `LevelPolicy` (không phải `DepartmentPolicy`): `viewAny`/`view` cho manager trả `true` không điều kiện, vì project/project-role không có field "sở hữu" kiểu `department_id` để scope. Admin bypass qua `before()`; create/update/delete admin-only (5.2 chỉ liệt kê "CRUD project" dưới Admin).
- `manageManagers` (thêm/gỡ PM) **admin-only**, kể cả với chính PM của project đó — "gán manager cho project" là việc của Admin theo 5.2, PM không tự thêm/gỡ đồng nghiệp.
- Ability `projects:read`/`project-roles:read` chỉ cấp cho admin+manager (không cấp employee), mirror `departments:read`/`levels:read`.
- Không thêm default status filter cho `GET /api/projects` (chỉ optional equality filter) — khác với Department's `status=active` default — vì Project có 4 trạng thái lifecycle (không phải binary active/inactive) nên không có "default" hiển nhiên, và mục 7 không yêu cầu cụ thể.

**Guard tích hợp `EmployeeService`:** `EmployeeService::delete()` và nhánh `status → resigned` trong `upsert()` đều gọi `ProjectManagerGuard::projectsLeftWithoutManagerIfRemoved()` trước khi thực hiện, throw `ValidationException` (422, liệt kê tên project) nếu employee là active PM duy nhất của project nào đó. Chưa có cascade auto-đóng `project_assignment`/`assignment_role_period` khi resign (mục 2 có nhắc) — đúng như dự kiến, vì 2 bảng đó là Phase C, chưa tồn tại.

### 7.6. Ghi chú triển khai Phase C backend (đã xong 2026-08-10)

**4 điểm mở trong mục 3 đã hỏi lại user và chốt trước khi code:**
- Tạo assignment **bắt buộc `role_ids` (mảng, tối thiểu 1)** ngay lúc tạo — mirror đúng tinh thần `manager_employee_ids` bắt buộc ở Phase B, tạo assignment + role period(s) trong cùng 1 transaction.
- Điều kiện project để nhận assignment mới: **`Planned` hoặc `Active`** (theo new_business.md mục 8.2, không theo mục 11 vốn nói chỉ `Active`) — chỉ chặn khi project `Completed`/`Cancelled`.
- Authorization tạo/kết thúc assignment + quản lý role period: **Admin hoặc PM đang active của chính project đó** (khác hẳn `manageManagers` ở Phase B vốn admin-only tuyệt đối) — thêm ability mới `manageAssignments` trên `ProjectPolicy` (không tạo Policy riêng, mirror cách `manageManagers` cũng sống trên `ProjectPolicy` dù là hành động của sub-resource).
- Cascade khi employee resign (đã định ở mục 2, bị hoãn ở 7.2 vì bảng chưa tồn tại): **làm luôn trong Phase C này** — thêm interface `ProjectAssignmentCloser`/`ProjectAssignmentCloserService` (cùng khuôn với `ProjectManagerGuard`/`ProjectManagerGuardService`), inject vào `EmployeeService`, gọi sau khi `employee->update()` thành công trong nhánh `status → resigned`.

**Quyết định không hỏi lại (suy ra từ pattern Phase B có sẵn):**
- Ending assignment/role period **không nhận `end_date` từ client**, luôn `today()` — mirror đúng `ProjectManagerService::remove()` không nhận date tuỳ chỉnh. Vì vậy không có `EndProjectAssignmentRequest`/`EndAssignmentRoleRequest` — 2 action `destroy` dùng thẳng route model binding, không qua FormRequest.
- Route ending dùng `DELETE` dù bản chất là update (`end_date`/`status` mutate, không xoá gì) — cùng lý do đã ghi ở 7.2 cho `project_managers`.
- `{assignment}`/`{rolePeriod}` bind unscoped theo id (không nested-scope), verify `project_id`/`project_assignment_id` khớp thủ công trong controller, throw `NotFoundHttpException` nếu lệch — mirror chính xác `ProjectManagerController::destroy`.
- Check "employee đã có active assignment trên project" và "role đã active trên assignment" đều nằm ở **Service** (`lockForUpdate()` + `ValidationException::withMessages()`), không phải FormRequest — cùng pattern `ProjectManagerService::add()`. Ngược lại, check "project phải Planned/Active" và "start_date không sau project end_date" cũng đặt ở Service (không phải `withValidator` trong FormRequest) vì đây là business-state check cần đọc `$project`, không phải format/existence check.
- Không giới hạn "assignment luôn phải có ≥1 role active" — khác hẳn invariant "project luôn phải có ≥1 PM active" ở mục 3.1. Plan không nêu invariant này cho assignment nên `endRole` không chặn khi đó là role cuối cùng.
- `source_approval_request_id` trên `assignment_role_periods` thêm dạng `unsignedBigInteger nullable` **không có FK constraint** (vì bảng `approval_requests` chưa tồn tại tới Phase D) — FK sẽ được thêm bằng migration riêng ở Phase D khi bảng đó xuất hiện.
- Ability mới `projects:manage-assignments` được thêm vào danh sách ability của `manager` trong `AuthService::abilitiesFor()` (PM có thể là bất kỳ employee active nào được thêm vào `project_managers`, nhưng ability-gate ở tầng Sanctum token vẫn giả định PM luôn là người có system role `manager`, giống cách `projects:read` cũng chỉ cấp cho `manager`) — admin đã có `['*']` nên không cần thêm.
- `work-history` đặt route `GET /employees/{employee}/work-history`, controller action nằm trên `EmployeeController` (không tạo controller riêng), gọi vào `ProjectAssignmentService::workHistory()`, authorize bằng `Gate::authorize('view', $employee)` — tái dùng đúng `EmployeePolicy::view` (self, manager cùng department, admin) thay vì tự nghĩ rule mới.

**Phát sinh khi code, không lường trước lúc lập plan:**
- `project_assignments.created_by` là FK NOT NULL → mọi test HTTP tạo assignment phải dùng actor **đã persist** (`Employee::factory()->create()`), khác với `ProjectCrudTest`/`ProjectManagerTest` hiện có vốn dùng `Employee::factory()->make()` cho actor admin (vì Project/ProjectManager không lưu `created_by`). `tests/Feature/ProjectAssignmentTest.php` đổi sang `->create()` kèm comment giải thích, không đụng tới 2 file test cũ.
- Migration `assignment_role_periods` với `$table->index(['project_assignment_id', 'project_role_id'])` không đặt tên tường minh sinh ra tên index tự động dài hơn giới hạn 64 ký tự của MySQL (`assignment_role_periods_project_assignment_id_project_role_id_index`) — chỉ lộ ra khi chạy `tests/Feature/ImportMysqlConcurrencyTest.php` (test suite duy nhất chạy trên MySQL thật thay vì SQLite in-memory). Đã sửa bằng tên index tường minh ngắn hơn (`assignment_role_periods_assignment_role_index`).
- `AuthenticationTest::login_managerCredentials_abilitiesPersisted` hardcode nguyên mảng ability của manager — phải cập nhật thêm `projects:manage-assignments` vào đúng vị trí khi sửa `AuthService`.
- Full suite (343 test) chạy xong: 342 pass, 1 fail (`ImportDepartmentTest::importDepartments_unchangedRow_notRewritten`) — xác nhận đây là flake có sẵn từ trước, không liên quan Phase C (không đụng bảng/model nào của Project Assignment), pass lại bình thường khi chạy riêng lẻ.

### 7.3. Frontend theo phase

Mục 7 (và 7.1/7.2) mới chỉ track backend. Bổ sung scope FE cụ thể cho A/B/C — các phase D trở đi chưa thiết kế UI, để ngỏ tới khi làm tới nơi.

**Phase A — FE ✅ (2026-08-05):**
- `LevelsListPage.jsx` + `CreateLevelModal.jsx`/edit (mirror `DepartmentsListPage`/`CreateDepartmentModal`) — CRUD phẳng.
- Form tạo/sửa employee: thêm select `current_level_id` + search-select `manager_employee_id` (2 field admin-only, ẩn với manager/employee); field `status` cho phép admin **và** manager sửa (theo quyết định ở 7.1, không phải admin-only).
- `EmployeeProfileView.jsx`: hiển thị level hiện tại, tên manager, badge status.

**Phase B — FE ✅ (2026-08-05):**
- `ProjectsListPage.jsx` + `ProjectDetailPage.jsx` + `CreateProjectModal.jsx` (mirror Departments) — modal tạo bắt buộc chọn **≥1 PM** (multi-select, đúng `manager_employee_ids` dạng mảng đã chốt ở 7.2, không phải single-select).
- `ProjectRolesListPage.jsx` + modal (master data, mirror Levels) — CRUD phẳng.
- Component quản lý PM trong `ProjectDetailPage` — **không phải CRUD phẳng**: list PM active (tên + start_date), nút "Thêm PM", nút "Gỡ" từng dòng. Nếu đây là PM active cuối cùng → bắt hiện ô chọn PM thay thế trước khi cho xác nhận gỡ (khớp lỗi 422 từ BE khi thiếu `replacement_employee_id`); còn PM khác thì gỡ thẳng.

**Phase C — FE ✅ (2026-08-10):**
- `AssignmentsPanel.jsx` trong `ProjectDetailPage`: list assignment active (employee, role(s) dạng badge, khoảng ngày), nút "Thêm thành viên" (search toàn bộ employee qua `EmployeeSearchSelect` có sẵn + chọn role(s) qua `ProjectRoleMultiSelect` mới + start_date optional).
- Action "Kết thúc" cho cả assignment và từng role period riêng lẻ (mỗi role badge có nút `x`), cộng "+ Thêm vai trò" inline theo từng dòng.
- Component work-history (`WorkHistoryPanel.jsx`) — **không phải table phẳng**: timeline lồng nhau (project → các khoảng role bên trong, badge "Đang hoạt động"/"Đã kết thúc"), hiển thị trong `EmployeeProfilePage` ngay dưới `EmployeeProfileView`.

**Phase C.5 — Backend ✅ (2026-08-10), FE ✅ (2026-08-10):**

Đã thảo luận và chốt trước khi code (khác Phase A/B/C, không suy ra được từ pattern có sẵn nên hỏi lại):
- **Task list trong `ProjectDetailPage` là Kanban board** (cột theo `TaskStatus`, không phải bảng phẳng + filter kiểu `LeaveRequestsListPage`) — khớp câu "dashboard + kanban" đã ghi ở mục 7.
- **Chi tiết 1 task hiện trong modal** (`TaskDetailModal`), không phải route riêng — dùng chung ở 2 nơi: mở từ `TasksPanel` (kèm `canReview` = đúng biến `canManageAssignments` đã có sẵn trong `ProjectDetailPage`, vì điều kiện PM-của-project trong `TaskPolicy`/`TaskDelayRequestPolicy` là cùng 1 check) và mở từ panel "Nhiệm vụ được giao" trên `EmployeeProfilePage` (không có `canReview`, chỉ còn action tự-chuyển-trạng-thái của assignee).
- **Board không phân trang ở bản đầu** — 1 lần gọi `GET .../tasks?per_page=100`, group client-side theo status, không có "+ Xem thêm" per-column. Giới hạn 100 task/project chấp nhận được cho scope demo/portfolio (cùng tiền lệ `ProjectRoleMultiSelect` đã load 1 lần tới 100 dòng).
- **`TaskDelayRequestsListPage.jsx` — trang riêng, mirror `LeaveRequestsListPage` 1:1** — khớp câu chữ mục 5 "flat top-level, giống hệt leave-requests". Nút tạo request đặt trong `TaskDetailModal` (không phải trên trang list) vì cần context `due_date` hiện tại của đúng task đó.

Quyết định không hỏi lại (suy ra từ pattern có sẵn):
- Comment composer chỉ có `body`, không có UI gắn `task_status` ở bản đầu (YAGNI) — đổi trạng thái vẫn qua nút riêng độc lập với comment, đúng quyết định BE 7.9(b).
- Panel "Nhiệm vụ được giao" trên `EmployeeProfilePage` (mirror vị trí `WorkHistoryPanel`) chỉ đọc, không có nút tạo task (tạo task chỉ từ trong 1 project cụ thể qua `TasksPanel`).
- Progress badge (`done_count`/`total_count`/`overdue_count`) hiện cạnh badge status trên `ProjectDetailPage`.
- `ProjectMemberSearchSelect.jsx` (component mới, khác `EmployeeSearchSelect`) — search theo `GET /projects/{slug}/members`, dùng cho autocomplete `assigned_to` lúc tạo task, theo đúng mục 5.1 (2 component search riêng biệt).

**Phase D, E, F, G, H, I — FE: cần thảo luận thêm.** Chưa thiết kế UI cụ thể (task board/kanban, approval request list + step timeline, form request theo từng `change_mode`, approve/reject modal...) — để tới khi làm tới từng phase mới chốt, vì UX phụ thuộc feedback thực tế từ các phase trước.

### 7.4. Ghi chú triển khai Phase A frontend (đã xong 2026-08-05)

**`LevelsListPage` dùng inline-edit trong bảng, không phải modal edit riêng** — mirror đúng 1:1 `DepartmentsListPage` (input/select ngay trong ô bảng, `CreateLevelModal` chỉ dùng để tạo mới) thay vì suy diễn "CreateLevelModal.jsx/edit" thành một modal edit riêng biệt. Route `/levels` mở cho cả admin lẫn manager (`roles={['admin', 'manager']}`) vì `LevelPolicy::viewAny` trả `true` cho manager không điều kiện (khác `DepartmentPolicy`), nhưng cột "Hành động" (sửa/xoá) và nút "Thêm cấp bậc" chỉ render khi `isAdmin` — khớp `create`/`update`/`delete` admin-only qua `before()`.

**Component mới `EmployeeSearchSelect.jsx`** (debounced type-ahead qua `GET /employees?name=`, hiển thị kết quả dạng dropdown, chọn xong render thành "chip" có nút bỏ chọn) — dùng chung cho cả `CreateEmployeeModal` và `EmployeeProfileView` khi chọn `manager_employee_id`, tránh viết trùng logic tìm-kiếm-nhân viên ở 2 nơi. Không thêm dependency combobox nào (vẫn thuần React state + Tailwind, đúng mục 1.3).

**`EmployeeProfileView` tách 2 mức quyền sửa** thay vì dùng chung 1 biến `canEditRestrictedFields` như trước: `canEditRestrictedFields` (admin-only — áp dụng cho `position`/`department_id`/`current_level_id`/`manager_employee_id`) và `canEditStatus` (admin **hoặc** manager — chỉ áp dụng cho `status`), khớp đúng `EmployeeService::ALLOWED_UPDATE_FIELDS_BY_ROLE` đã chốt ở 7.1. Trường `status` luôn hiển thị dạng badge khi không ở chế độ sửa, giống cách `position` đã hiển thị.

**Nav "Cấp bậc"** thêm vào `headerNavItems()` cho cả admin và manager (không thêm cho employee, khớp ability `levels:read` chỉ cấp cho 2 role này).

Đã verify thủ công qua browser (không có test tự động cho FE trong repo này): tạo/sửa level (admin), xem levels read-only (manager, không thấy nút mutate), tạo employee kèm `current_level_id` + `manager_employee_id` qua search-select, xem/sửa profile hiển thị đúng level/manager/status, manager sửa được `status` nhưng không sửa được level/manager/department/position trên cùng 1 profile. `npm run lint` sạch (đã fix 1 lỗi `react-hooks/set-state-in-effect` trong `EmployeeSearchSelect` bằng eslint-disable-next-line, theo đúng pattern đã dùng ở `EmployeeProfilePage`).

### 7.5. Ghi chú triển khai Phase B frontend (đã xong 2026-08-05)

**Route access mirror `LevelPolicy`, không phải `DepartmentPolicy`:** `/projects`, `/projects/:slug`, `/project-roles` đều mở cho `roles={['admin', 'manager']}` vì `ProjectPolicy::viewAny`/`ProjectRolePolicy::viewAny` trả `true` cho manager không điều kiện (đúng ghi chú 7.2). Khác với Levels, `ProjectPolicy::update`/`manageManagers` **admin-only không có ngoại lệ** (không giống Department cho phép manager sửa phòng ban của chính mình) — nên `ProjectDetailPage` với manager hoàn toàn read-only: không có nút "Cập nhật", "Thêm PM", hay "Gỡ" nào hiển thị, chỉ admin mới thấy.

**`ProjectsListPage` inline-edit chỉ name + status** (mirror đúng `DepartmentsListPage`, không phải `LevelsListPage`) — vì Project có nhiều field hơn (`description`, `start_date`, `end_date`, `managers`) nên form đầy đủ dồn vào `ProjectDetailPage`, list chỉ cho sửa nhanh 2 field hay đổi nhất. Cột "Project Manager" trong list join tên các PM đang active (`end_date === null`) bằng dấu phẩy, không hiển thị PM đã kết thúc. Filter status mặc định **"Tất cả"** (không gửi param `status`) thay vì "active" như Department/Level — khớp quyết định 7.2 rằng Project có 4 trạng thái lifecycle nên không có default hiển nhiên.

**`ProjectRolesListPage` inline-edit cả 3 field** (name/description/status) thay vì chỉ name+status — mirror đúng `LevelsListPage` (không phải Department) vì plan chỉ định rõ "mirror Levels". `description` dùng `<input>` một dòng khi inline-edit (không phải `<textarea>`) để giữ chiều cao hàng bảng gọn, khác với modal tạo mới vẫn dùng `<textarea>` 3 dòng.

**2 component search-select employee mới, tách theo nhu cầu chọn 1 hay nhiều:**
- `EmployeeMultiSelect.jsx` (mới) — dùng cho `manager_employee_ids` lúc tạo project (bắt buộc ≥1, chip nhiều lựa chọn, đã lọc client-side những employee đã chọn khỏi dropdown thay vì gọi lại API mỗi lần thêm/bớt chip, tránh round-trip thừa).
- `EmployeeSearchSelect.jsx` (tái dùng từ Phase A) — dùng cho "Thêm PM" (chọn 1) và ô "PM thay thế" khi gỡ PM cuối cùng (thêm `excludeId` = chính PM đang bị gỡ, tránh tự thay thế bằng chính mình).

**`ProjectManagersPanel.jsx` (component riêng, không nhét vào `ProjectDetailPage`)** — implement đúng theo mô tả mục 7.3: còn >1 PM active thì gỡ thẳng qua `window.confirm`; đúng 1 PM active thì ẩn `window.confirm`, mở khối chọn PM thay thế inline (nền vàng cảnh báo) và chỉ enable nút "Xác nhận gỡ" khi đã chọn — chủ động chặn trước ở FE thay vì để văng lỗi 422 `replacement_employee_id` từ BE rồi mới xử lý qua toast. `onChanged` callback luôn refetch nguyên `GET /projects/{slug}` sau khi thêm/gỡ PM thay vì cố merge response cục bộ, vì `POST .../managers` chỉ trả về 1 `ProjectManagerResource` còn `DELETE .../managers/{id}` trả nguyên `ProjectResource` — 2 shape khác nhau nên refetch đơn giản và đúng hơn là hợp nhất state thủ công.

Đã verify thủ công qua browser: tạo project với 2 PM qua multi-select, gỡ PM khi còn >1 (qua `window.confirm`), gỡ PM cuối cùng bắt chọn thay thế trước khi cho xác nhận (verify cả 2 nhánh của `ProjectManagerService::remove`), sửa project (name/status) ở cả list lẫn detail, CRUD `project-roles`, và xác nhận manager chỉ xem được (không thấy bất kỳ nút mutate nào) ở cả 3 trang mới. `npm run lint` sạch, không cần eslint-disable mới.

---

### 7.7. Ghi chú triển khai Phase C frontend (đã xong 2026-08-10)

**`canManageAssignments` tính riêng, khác `canManage`** — `ProjectDetailPage.jsx` giờ có 2 biến quyền: `canManage` (admin-only, dùng cho "Cập nhật" project + `ProjectManagersPanel`, không đổi so với Phase B) và `canManageAssignments` (admin **hoặc** đang là PM active của chính project đó — check `project.managers.some(m => !m.end_date && m.employee_id === user.id)`), khớp đúng quyết định 7.6 rằng PM cũng được quản lý assignment/role period. Verify qua browser xác nhận: PM không phải admin (position `manager`, được thêm làm PM qua "Thêm PM") thấy và dùng được nút "Thêm thành viên" — request `POST .../assignments` trả 201, đi qua đúng cả 2 lớp (Sanctum ability `projects:manage-assignments` + `ProjectPolicy::manageAssignments`).

**`ProjectRoleMultiSelect.jsx` (component mới) khác hẳn `EmployeeMultiSelect`** — không debounce/search vì `project_roles` là master data nhỏ (~9 dòng cố định), load 1 lần lúc mount (`listProjectRoles({status:'active', per_page:100})`), hiển thị dạng checkbox-pill thay vì input gõ tìm + dropdown. Class động tính trong JS (`isSelected ? '...' : '...'`) thay vì dùng biến thể CSS `has-checked:` của Tailwind v4 — tránh phụ thuộc vào một pattern CSS chưa từng xuất hiện ở nơi khác trong codebase mà không verify được.

**`AssignmentsPanel.jsx` dùng `useCursorList` với `params: {status: 'active'}` cố định** — không có UI filter đổi status, khớp đúng scope 7.3 chỉ yêu cầu "list assignment active". Sub-component `AssignmentRow` định nghĩa ngay trong cùng file (không tách file riêng) vì chỉ dùng nội bộ, mirror cách `ProjectManagersPanel` cũng gói gọn 1 file. Mỗi `AssignmentRow` tự fetch `listProjectRoles` riêng (không nâng state lên panel cha) khi bấm "+ Thêm vai trò" — chấp nhận gọi lại API nếu mở nhiều dòng, ưu tiên đơn giản hơn tối ưu vì đây chỉ là on-demand, không phải load lúc mount.

**Cả 2 action "Kết thúc" (assignment và role period) đều không nhận `end_date` tuỳ chỉnh từ UI** — khớp quyết định BE 7.6 (`endProjectAssignment`/`endAssignmentRole` trong `api/projectAssignments.js` không có tham số ngày), chỉ `window.confirm` rồi gọi thẳng, mirror đúng pattern `ProjectManagersPanel`.

**Phát sinh khi code:** `canManageAssignments` phải tính bằng optional chaining (`project?.managers ?? []`) thay vì `project.managers` trực tiếp — `project` còn `null` ở lần render đầu tiên (trước khi `load()` resolve), khác với `canManage` vốn không phụ thuộc `project` nên không gặp vấn đề này.

Đã verify thủ công qua browser (chạy `laravel-backend` + `fe-vite-project` từ `.claude/launch.json`, migrate 2 bảng mới lên DB dev MySQL trước — 2 migration trước đó chỉ áp dụng cho SQLite test): thêm thành viên kèm role vào project "Intern" (admin), kết thúc 1 role rồi kết thúc cả assignment (thấy đúng badge "Đã kết thúc" trong work-history), xem `WorkHistoryPanel` trên `EmployeeProfilePage` hiển thị đúng timeline lồng nhau, và xác nhận PM không phải admin (thêm tạm 1 PM `manager`-position để test, đã xoá lại sau khi xong) cũng thêm được thành viên — đúng nhánh PM của `manageAssignments`. `npm run lint` sạch, không cần eslint-disable mới. Dữ liệu test (2 assignment đã ở trạng thái `ended`) được để lại trong DB dev như debris vô hại, cùng kiểu với các account `Test Manager`/`Test Employee` đã có sẵn từ trước.

---

### 7.8. Đợt bugfix/UX sau khi user tự kiểm tra app (đã xong 2026-08-10)

10 điểm user note sau khi đi kiểm tra app một vòng (không thuộc phase A/B/C/C.5 nào cụ thể — sửa lỗi + UX nhỏ trên nền đã có). 4 điểm mở đã hỏi lại user và chốt trước khi code:

- **Work-history route**: giữ nguyên response/logic, chỉ đổi path `GET /employees/{employee}/work-history` → `GET /employees/{employee}/projects` (controller method `workHistory` không đổi tên).
- **Bulk-assign**: `StoreProjectAssignmentRequest` đổi `employee_id` (số ít) → `employee_ids` (mảng, `distinct`, tối thiểu 1). `ProjectAssignmentService::create()` tạo tất cả trong 1 transaction, all-or-nothing — 1 employee đã active sẵn thì rollback toàn bộ batch, không skip riêng người đó. Trả về `EloquentCollection<ProjectAssignment>` thay vì 1 model đơn, controller dùng `ProjectAssignmentResource::collection()`.
- **Rank gap-based**: `LevelService` thêm `RANK_GAP=10` + `REBALANCE_SCRATCH_OFFSET=60000`. Tạo level mới không nhận `rank` trực tiếp nữa mà nhận `insert_position` (`start`/`end`/`before`/`after`) + `reference_level_id`, BE tính rank = trung điểm 2 láng giềng tương lai. Hết chỗ trống (2 rank liền kề) thì rebalance toàn bộ về bội số của `RANK_GAP` theo đúng thứ tự hiện tại rồi tính lại — rebalance đi qua 2 pha (dồn vào scratch range trước) vì gán tuần tự 1 pha sẽ đụng unique constraint khi rank đích của dòng này trùng rank hiện tại của dòng khác chưa tới lượt. **Chỉ áp dụng cho tạo mới** — sửa level qua inline-edit ở `LevelsListPage` vẫn nhập `rank` trực tiếp như cũ (không đổi, vì user chỉ note "chức năng tạo rank").
- **Employee xem project**: `ProjectPolicy::view()` cho phép employee thường xem nếu có assignment (active hoặc ended — theo quyết định "cả project đã từng tham gia"), `viewAny` vẫn admin/manager-only nên employee không browse được `/projects` list, chỉ vào thẳng 1 project cụ thể họ có assignment. Phải thêm `projects:read` vào `AuthService::abilitiesFor()` cho position mặc định (employee) — thiếu bước này thì Sanctum ability gate chặn trước khi tới Policy dù Policy đã cho phép. FE: route `/projects/:slug` tách ra khỏi group `roles={['admin','manager']}`, mở cho mọi role đã đăng nhập; `WorkHistoryPanel` thêm `project_slug` vào `EmployeeWorkHistoryResource` để tên project trong "Quá trình làm việc" bấm được thẳng tới trang chi tiết.

**Quyết định không hỏi lại (suy ra từ pattern có sẵn):**
- `created_by` → `assigned_by` trên `ProjectAssignment`: sửa thẳng trong migration `2026_08_10_090000_create_project_assignments_table.php` (không tạo migration rename mới) vì bảng này chưa từng deploy — cùng lý do migration Phase C được sửa trực tiếp lúc còn trong cùng đợt code.
- `/me` không còn tự render `EmployeeProfileView` — đổi thành `<Navigate to={`/employees/${user.id}`} replace />`, xoá hẳn code trùng thay vì thêm `WorkHistoryPanel` vào cả 2 nơi. Đây là lý do gốc của bug mục 9: `MePage` được viết trước `WorkHistoryPanel` tồn tại nên chưa bao giờ có nó.
- Filter project theo PM (`manager_employee_id`) chỉ match **active** manager (`whereHas('activeManagers', ...)`), không tính PM đã bị gỡ — khớp cách cột "Project Manager" trong list vốn đã chỉ hiển thị active manager.
- "Xem chi tiết" bị bỏ ở cả 3 trang list (Departments/Employees/Projects) — không chỉ Projects — vì user note rõ "áp dụng cho cả các màn hình khác". Cột "Hành động" ở Employees/Projects ẩn hẳn (không chỉ ẩn nút) khi user không phải admin, vì sau khi bỏ "Xem chi tiết" thì cột đó không còn gì để hiện với non-admin — cùng pattern `LevelsListPage` đã dùng từ Phase A.

**Phát sinh khi verify qua browser:** DB dev MySQL (dùng chung bởi 2 phiên Claude Code song song trên cùng thư mục qua `php artisan serve` đã chạy sẵn ở port 8000) vẫn còn cột `created_by` cũ dù migration file đã sửa — vì migration chỉ chạy 1 lần, sửa file không tự động áp lại schema đã tồn tại. Phải chạy `ALTER TABLE project_assignments CHANGE created_by assigned_by BIGINT UNSIGNED NOT NULL` thủ công qua tinker để đồng bộ, không ảnh hưởng FK constraint. Toàn bộ 356 test (bao gồm cả test mới cho 10 điểm trên) pass trên DB test SQLite riêng, không liên quan tới drift này.

---

### 7.11. Đợt bugfix/UX sau khi user tự kiểm tra C.5 FE (đã xong 2026-08-10)

3 điểm user note sau khi đi kiểm tra tính năng Task Management một vòng:

- **`TaskPolicy::comment()` cho broad-manager-bypass, không giới hạn theo PM của project** — user bắt được qua thao tác comment: 1 manager phòng ban (không phải PM của project đó) vẫn comment được trên task của project họ không quản lý, do `comment()` cũ delegate thẳng sang `view()` (vốn cố ý cho mọi manager xem mọi task, mirror `ProjectPolicy::view`). Sửa: `comment()` giờ tự kiểm tra riêng (PM của project, hoặc assignee, hoặc project member) thay vì tái dùng `view()` — thêm helper `isProjectManager()` dùng chung với `create()`/`update()` (trước đó viết lặp lại 2 lần). **`view()` giữ nguyên** (manager vẫn xem được mọi task, đúng ý user "chỉ xem"). Đã audit toàn bộ `app/Policies/*Task*` — đây là chỗ duy nhất có lỗ hổng broad-manager-bypass cho hành động ghi (`create`/`update`/`TaskDelayRequestPolicy` đều đã tự siết theo `isProjectManager` từ đầu, không qua `view()`). Cập nhật test `TaskCommentTest.php`: đổi `createComment_projectManagerNotAssignee_created` (manager bất kỳ) thành 2 test riêng — `createComment_projectOwnManager_created` (PM thật của project) và `createComment_managerNotProjectManagerNorMember_forbidden` (manager ngoài cuộc, giờ 403).
- **Comment và ghi chú duyệt/từ chối gộp làm 1** — trước đó `review_note` chỉ nhập được lúc PM từ chối (`InReview → InProgress`), tách biệt hẳn với comment thread. Đổi sang: 1 ô textarea chung "Ghi chú kèm theo khi đổi trạng thái" hiện phía trên mọi nút chuyển trạng thái (cả tự-chuyển của assignee lẫn duyệt/từ chối/hủy của PM) — khi có nội dung, `TaskDetailModal` vừa gửi kèm `review_note` trong `PATCH /tasks/{id}` (BE chỉ thực sự lưu khi transition ra khỏi `InReview`, các transition khác bỏ qua giá trị này — không cần đổi BE) vừa tự động `POST /tasks/{id}/comments` với `task_status` = trạng thái đích, để employee đính kèm PR/link lúc "Nộp duyệt" và mọi thay đổi trạng thái đều để lại trace trong comment thread. Không đổi API/schema BE nào — thuần FE gọi tuần tự 2 endpoint đã có sẵn.
- **UX Kanban card + label/value contrast** — thêm `TASK_STATUS_CARD_CLASSES` (mirror hue với `TASK_STATUS_BADGE_CLASSES`, nhạt hơn 1 bậc: `todo` trắng, `in_progress` cam nhạt, `in_review` vàng nhạt, `done` xanh lá nhạt, `cancelled` xám nhạt) áp cho background từng card trong `TasksPanel`; đồng thời đổi `in_progress` từ xanh dương sang cam để card và badge dùng chung 1 bảng màu nhất quán theo status. `TaskDetailModal`: cặp "Giao cho:"/"Hạn:" tách label (nhạt, `text-gray-400`) và value (đậm, `font-medium text-gray-700`) thay vì dùng chung 1 class như trước.

**Phát sinh khi verify (không thuộc 3 điểm trên):** phát hiện thêm 1 flaky test có sẵn không liên quan — `TaskDelayRequestTest::getDelayRequests_employeeActor_scopedToOwnRequests` tạo actor bằng `Employee::factory()->create(['status' => 'active'])` không chỉ định `position`, trong khi `EmployeeFactory` random 50/50 giữa `employee`/`manager` — khi ra `manager` thì test fail vì `GetTaskDelayRequestsRequest` chỉ ép `requested_by=self` cho `position=employee`. Sửa bằng cách chỉ định rõ `'position' => 'employee'` trong test, không đụng code sản xuất. Toàn bộ 397 test (test suite đầy đủ, không chỉ filter Task) pass sau khi sửa cả 3 điểm + flake này; `vendor/bin/pint --dirty` sạch; verify lại qua browser (đăng nhập `Test Employee` — đồng thời là PM của project trong data test nên thấy cả nút tự-chuyển lẫn nút duyệt): xác nhận ghi chú "Bắt đầu làm"/"Nộp duyệt"/"Từ chối" đều tự tạo comment đúng nội dung, `review_note` vẫn hiển thị song song, card "Hoàn thành" có `bg-green-50`, "Giao cho:"/"Hạn:" hiển thị đúng contrast nhạt/đậm.

### 7.10. Ghi chú triển khai Phase C.5 frontend (đã xong 2026-08-10)

Phạm vi: `TasksPanel.jsx` (Kanban board, nhúng vào `ProjectDetailPage`) + `TaskDetailModal.jsx` (dùng chung với `EmployeeTasksPanel.jsx` trên `EmployeeProfilePage`) + `CreateTaskModal.jsx` + `ProjectMemberSearchSelect.jsx` + `TaskDelayRequestsListPage.jsx`, cộng các file `api/`/`lib/` tương ứng. Các quyết định UX (Kanban/Modal/no-pagination/dedicated delay-request page) đã chốt qua thảo luận trước khi code, xem block "Đã thảo luận và chốt" ở mục 7.3 phía trên.

**Phát sinh khi code, không lường trước lúc thảo luận:**
- **`TaskDelayRequestsListPage` chỉ cho `admin` bấm Duyệt/Từ chối, không cho `manager`** — phát hiện khi thiết kế: `GetTaskDelayRequestsRequest` không ép manager theo project họ quản lý (mục 7.9 đã ghi rõ "đây chỉ là danh sách read-only" ở tầng BE), nên 1 manager bất kỳ có thể thấy request của project họ không phải PM. Hiện nút Duyệt/Từ chối cho mọi manager sẽ tạo nút chắc chắn 403 khi bấm nhầm request ngoài phạm vi PM của họ. Quyết định: ẩn hẳn 2 nút này với manager trên trang list phẳng, PM chỉ duyệt/từ chối qua `TaskDetailModal` mở từ chính project board của họ (nơi `canReview` được tính đúng theo từng project qua `canManageAssignments`). Admin luôn thấy đủ nút vì bypass mọi check qua `before()`.
- **Progress badge (`done_count`/`total_count`/`overdue_count`) trên `ProjectDetailPage` bị stale sau khi tạo/cập nhật task** — vì các số này nằm trên `project` state (chỉ refetch khi vào lại trang), còn `TasksPanel` giữ `tasks` state riêng. Sửa bằng cách thêm prop `onChanged` cho `TasksPanel` (gọi sau mọi lần tạo/cập nhật task), truyền thẳng `load` có sẵn của `ProjectDetailPage` xuống — cùng pattern `ProjectManagersPanel` đã dùng, thay vì tự tính lại 3 con số này ở FE (sẽ phải chép lại đúng công thức `overdue` của BE, vi phạm DRY).
- **`react-hooks/set-state-in-effect` lint error** ở cả `TasksPanel` (gọi `load()` trực tiếp làm effect) và `TaskDetailModal` (gọi `loadComments()`/`loadDelayRequests()` trong effect) — sửa bằng `eslint-disable-next-line` ngay trên dòng gọi hàm đầu tiên, đúng pattern đã dùng ở `ProjectDetailPage`/`EmployeeProfilePage`. Chỉ dòng đầu cần disable (eslint chỉ báo 1 lỗi/effect dù có nhiều setState-call phía sau).
- **`EmployeeTasksPanel` không hiển thị link tới project** (chỉ hiện tên project dạng text) — dự định ban đầu bọc tên project trong `<Link>` như `WorkHistoryPanel` đã làm, nhưng phát hiện điều đó tạo `<a>` lồng trong `<button>` (row cả dòng là 1 button để mở modal) — HTML không hợp lệ. Bỏ link, giữ text thường; `TaskDetailModal` cũng chỉ hiện `project_name` dạng text, không link, để nhất quán.
- Đã verify thủ công qua browser (dựng server FE riêng ở port 5199 do backend/frontend mặc định của `.claude/launch.json` đã bị 1 phiên Claude Code khác chiếm, autoPort của Browser tool bị lệch port thật vite bind — chạy `npm run dev -- --port 5199 --strictPort` trực tiếp rồi trỏ Browser pane vào URL đó): tạo task kèm assignee qua `ProjectMemberSearchSelect` (admin), luồng tự chuyển trạng thái `Todo→InProgress→InReview` (đăng nhập `Test Employee`/`password`), tạo yêu cầu gia hạn (assignee), duyệt gia hạn (admin, xác nhận `due_date` trên task cập nhật đúng theo yêu cầu), từ chối task từ `InReview` kèm `review_note` (admin, quay về `InProgress` đúng), post bình luận, và xem `TaskDelayRequestsListPage` từ cả 2 phía (employee chỉ thấy đơn của mình + nút Hủy, admin thấy toàn bộ + đủ 3 nút). `npm run lint` sạch.

### 7.9. Ghi chú triển khai Phase C.5 backend (đã xong 2026-08-10)

Phạm vi: `Task` + `TaskComment` + `TaskDelayRequest` (migration/model/enum/policy/service/request/resource/controller/route), cộng `GET /projects/{project}/members` (mục 5.1) và `done_count`/`total_count`/`overdue_count` trên `ProjectResource` (mục 5). Chưa làm frontend — theo đúng quyết định đã hỏi lại user trước khi bắt đầu (mục 7.3 vẫn ghi "cần thảo luận thêm" cho C.5, UI kanban/board chưa thiết kế).

**Quyết định không hỏi lại (suy diễn từ mục 5 + pattern có sẵn):**
- Authorization "tạo/assign = PM của project hoặc admin" đặt thẳng trên `TaskPolicy::create(Employee $employee, Project $project)`, gọi qua `Gate::authorize('create', [Task::class, $project])` — khác với `manageManagers`/`manageAssignments` ở Phase B/C vốn sống trên `ProjectPolicy`. Đã đọc `vendor/laravel/framework/.../Gate.php` để xác nhận hành vi: khi arguments là mảng `[Task::class, $project]`, Laravel tự động dùng phần tử đầu (class-string) để resolve `TaskPolicy` rồi **loại bỏ** nó trước khi gọi method — nên `create()` chỉ nhận `($employee, $project)`, không nhận `Task::class`. Đây là convention chuẩn của Laravel, dùng lần đầu trong codebase vì đây là action "create" đầu tiên cần thêm context ngoài class; cùng pattern áp dụng cho `Gate::authorize('create', [TaskDelayRequest::class, $task])`.
- `TaskPolicy::view()` mirror đúng `ProjectPolicy::view()`: manager xem được mọi task không điều kiện (không cần là PM của project đó); employee xem được nếu là assignee hoặc có (từng có) assignment trên project của task đó. Method `comment()` tái dùng thẳng `view()` — ai xem được task thì cũng comment được, vì mục 5 không có rule riêng cho comment. Hệ quả: 1 manager bất kỳ (không cần là PM) cũng comment được trên task của project họ không quản lý — chấp nhận được vì cùng logic "manager thấy mọi project" đã áp dụng nhất quán từ Phase B.
- `task_comments.task_status` là tag hiển thị **client tự set khi post comment**, không phải do server tự sinh khi `PATCH /tasks/{task}` đổi status. Cân nhắc 2 hướng: (a) `TaskService::updateStatus()` tự tạo 1 `TaskComment` trace mỗi lần đổi status, hoặc (b) để FE tự quyết có kèm comment hay không. Chọn (b) vì mục 5 mô tả bảng này "vừa là comment tự do, vừa đóng vai trò trace" chứ không bắt buộc mọi lần đổi status đều phải có comment kèm theo, và (a) sẽ buộc `body` phải có giá trị mặc định giả tạo khi user không nhập gì — giữ 2 endpoint (`PATCH /tasks/{task}` và `POST /tasks/{task}/comments`) độc lập, đơn giản hơn, đúng tinh thần SRP.
- Delay-request chỉ được tạo bởi **chính assignee của task** (mirror `LeaveRequestPolicy::create` — self-service, không cho PM tạo hộ), khác với `manager_employee_ids`/`role_ids` ở Phase B/C vốn admin/PM chỉ định người khác. Validate nghiệp vụ (task không phải Done/Cancelled, task phải có sẵn `due_date`, `requested_due_date` phải sau `due_date` hiện tại) đặt ở `TaskDelayRequestService::create()` — không phải FormRequest — vì đều cần đọc `$task` từ DB, cùng nguyên tắc đã áp dụng ở 7.6 cho check "project phải Planned/Active".
- `GetTaskDelayRequestsRequest` chỉ ép `requested_by = self` khi actor là `employee`; manager/admin xem được toàn bộ danh sách (không lọc theo project họ quản lý) — chấp nhận lỏng hơn `GetLeaveRequestsRequest` (vốn ép manager theo `department_id`) vì "PM của project nào" không có cột trực tiếp trên `task_delay_requests`, phải join qua `task→project→managers`, và đây chỉ là danh sách read-only — quyền approve/reject thật sự vẫn bị `TaskDelayRequestPolicy::update()` chặn đúng theo PM của project đó.
- `done_count`/`total_count`/`overdue_count` chỉ được `loadCount()` trong `ProjectController::show()`, không đụng tới `index()` — đúng câu chữ mục 5 ("`GET /api/projects/{project}` trả kèm... không tách endpoint `/progress` riêng") và tránh N+1 khi list nhiều project. `ProjectResource` dùng `$this->when(isset(...))` nên field không xuất hiện trong response list.
- `GET /projects/{project}/members` (mục 5.1) đặt route/controller riêng (`ProjectMemberController`) thay vì nhét vào `ProjectAssignmentController`, nhưng query logic (`searchMembers()`) đặt trong `ProjectAssignmentService` vì đọc từ quan hệ `project_assignments` — tách controller theo shape response (Employee, không phải Assignment) nhưng giữ chung service theo nguồn dữ liệu. Gate bằng `Gate::authorize('view', $project)` + ability `projects:read` có sẵn, không thêm ability mới.
- Ability mới (`tasks:read`/`create`/`update`, `task-comments:read`/`create`, `task-delay-requests:read`/`create`/`update`) cấp cho **cả** `manager` và `employee` (default) bucket trong `AuthService::abilitiesFor()`, **kể cả `tasks:create`** — khác tiền lệ 7.6 (ability theo vai trò PM chỉ cấp cho system role `manager`) vì PM của 1 project cụ thể được xác định qua `project_assignments`/`ProjectManager` (`TaskPolicy::isProjectManager()`), không qua cột `employees.position` — một `employee` (position) hoàn toàn có thể là PM của project họ được gán, và middleware `abilities:tasks:create` phải cho request đó lọt qua trước khi tới `Gate::authorize('create', $project)` (nơi mới thật sự chặn theo đúng project). Nếu bucket `employee` thiếu `tasks:create`, employee-là-PM sẽ bị 403 ngay ở middleware dù policy lẽ ra cho phép. Đã cập nhật `AuthenticationTest::login_employeeCredentials_abilitiesPersisted` (hardcode nguyên mảng ability) + thêm test `TaskCrudTest::createTask_employeeAsProjectManager_created` (position `employee`, không phải `manager`) để khóa lại đúng case này.
- `task_comments` không có `updated_at` (`const UPDATED_AT = null` trên model + `$table->timestamp('created_at')->useCurrent()` trên migration, không dùng `$table->timestamps()`) — đúng câu chữ mục 5 "không có update/delete ở bản đầu (create + list only)".
- 3 migration Task đặt timestamp `2026_08_10_120000+` (sau 2 migration Phase C `090000`/`090001` cùng ngày) dù `artisan make:migration` sinh ra timestamp sớm hơn — đổi tên file thủ công cho đúng thứ tự thời gian thực tế, dù không có FK phụ thuộc nào giữa `tasks` và `project_assignments`/`assignment_role_periods` nên thứ tự này không bắt buộc về mặt kỹ thuật.

**Phát sinh khi code:**
- `ProjectMemberSearchTest` lúc đầu fail 3/4 test vì viết sai path assertion (`data.0.id` thay vì `data.data.0.id`) — quên rằng `ProjectMemberCollection` cũng bọc `{data, meta}` như mọi cursor-paginated resource khác trong app, không phải bug ở service/query. Sửa lại assertion, không đụng gì tới code sản xuất.
- Toàn bộ 396 test (bao gồm 40 test mới cho C.5) pass trên SQLite test DB; `vendor/bin/pint --dirty --format agent` sạch, không cần sửa thủ công.

---

## 8. Milestone demo đề xuất

Giữ nguyên tinh thần `new_business.md` mục 40: employee đang giữ role Backend ở project ERP → tạo request thêm role Tech Lead → PM duyệt → role period mới được tạo, không mất role cũ → request chuyển APPROVED → APPLIED → notify → thể hiện đầy đủ work-history. Có thể bổ sung thêm nhánh Task Management (tạo task, chuyển trạng thái, request delay, PM duyệt) làm milestone phụ vì hoàn thành trước và độc lập với Approval Engine.

## 9. Sidebar Layout & Dashboard (UI refactor)

Yêu cầu refactor toàn bộ layout FE (3 role: employee/manager/admin) sang dạng dashboard chuyên nghiệp hơn — sidebar dọc thay header ngang, cộng 1 trang Dashboard làm trang chủ mới hiển thị thông tin dùng nhiều nhất theo từng role. Không thuộc track "Project Management" (Phase A-I ở mục 7) — đây là UI/UX refactor cắt ngang, độc lập với track đó.

### 9.1. Quyết định đã chốt (trước khi code)

- **Sidebar** thay `Header.jsx` hoàn toàn: dọc bên trái, thu/mở được (state lưu `localStorage`). Icon dùng thư viện `@heroicons/react` (outline set) — ngoại lệ được user duyệt cho riêng phần icon, phá nguyên tắc "không thêm dependency FE mới" chỉ ở điểm này; chọn Heroicons vì khớp đúng style SVG đang có (icon chuông trong `NotificationBell.jsx` vốn đã "chép tay" theo đúng path Heroicons outline) và cùng nhà phát triển với Tailwind (đang dùng Tailwind v4). `NotificationBell` + tên/role badge + nút đăng xuất chuyển xuống cuối sidebar (không còn header để chứa).
- **`/dashboard` thay thế hoàn toàn trang chủ hiện tại**: `roleHomePath()` đổi thành trỏ `/dashboard` cho cả 3 role, thay vì `/departments` (admin) / `/departments/:slug` (manager) / `/me` (employee) như hiện tại. Các trang cũ giữ nguyên 100%, chỉ không còn là landing page sau đăng nhập — vẫn truy cập được bình thường qua sidebar.
- **Không thêm endpoint `/dashboard` tổng hợp ở BE**: mỗi widget tự fetch dữ liệu từ API đã có sẵn, độc lập với các widget khác — lý do: mỗi component thể hiện thông tin riêng, cần nút "làm mới" riêng từng widget mà không kéo lại (reload) cả trang.
- **Hook mới `useAsyncResource`** (đặt trong `hooks/`, mirror `useCursorList`): fetch 1 lần (không cursor/phân trang) + hàm `refresh()` + phân biệt `loading` (lần đầu, chưa có data) / `refreshing` (các lần sau, giữ data cũ hiển thị mờ thay vì trắng trang) + guard response cũ đè lên response mới qua `requestIdRef` — đúng cơ chế `useCursorList` đang dùng ở mọi trang list. Mỗi widget gọi hook này riêng 1 lần ⇒ state cô lập theo từng instance component, bấm "làm mới" ở 1 card không ảnh hưởng card khác — không cần thêm `AbortController` hay thư viện mới.
- **Ngoại lệ duy nhất cho BE**: thêm 1 endpoint self-service mới `GET /employees/{employee}/managed-projects`, mirror đúng cách work-history (`GET /employees/{employee}/projects`) đã làm — auth qua `Gate::authorize('view', $employee)` (self, admin, hoặc manager cùng phòng của employee đó), trả các project mà employee đó đang là PM active, kèm sẵn `done_count`/`total_count`/`overdue_count` (tái dùng `loadCount()` đã viết cho `ProjectController::show()`, áp lên cả collection thay vì 1 record). Widget "project đang quản lý kèm tiến độ" của **cả manager lẫn employee** đều gọi chung endpoint này (luôn là chính mình) thay vì `GET /projects` (list) — 2 lý do: (1) tránh N+1 vì `done_count`/`total_count`/`overdue_count` hiện chỉ có ở `show()`, không có ở `index()` (mục 7.9); (2) **quan trọng hơn**: `ProjectPolicy::viewAny()` hiện chỉ `true` khi `position === 'manager'` (admin bypass qua `before()`) — 1 `employee` dù được thêm làm PM của project nào đó (`project_managers` không ràng buộc theo `position`, đã xác nhận qua đọc code) vẫn bị 403 ở `GET /projects` bất kể filter gì, nên route chung không dùng được cho trường hợp employee làm PM. Endpoint self-service mới né hẳn vấn đề này, không phải nới lỏng `viewAny` (tránh rủi ro vô tình mở list toàn bộ project cho employee).
- **Không nhúng nguyên component nghiệp vụ cũ vào dashboard** — đặc biệt Kanban board (`TasksPanel`): đã đo thực tế cần tối thiểu ~1050px (5 cột `min-w-[200px]` + gap) và **đã tự cuộn ngang ngay trong trang hiện tại** (`ProjectDetailPage` đang bị khoá `max-w-5xl` = 1024px, verify qua browser ở viewport 1280px). Widget dashboard chỉ hiện dạng rút gọn (đếm theo cột/status + top-N task sắp/quá hạn), không phải nhúng board đầy đủ — mục tiêu không có widget nào trong dashboard cần cuộn ngang.
- **Trình tự triển khai tách 2 giai đoạn**: Giai đoạn 1 = sidebar layout (thay đổi cơ học, không phụ thuộc thiết kế widget). Giai đoạn 2 = nội dung dashboard theo role, làm sau khi giai đoạn 1 xong.

### 9.2. Đề xuất nội dung widget theo role (điểm khởi đầu cho Giai đoạn 2, chưa phải chốt cuối)

- **Employee**: đếm task theo status (Todo/In Progress/In Review), task sắp/đã quá hạn (top N), project đang tham gia (từ work-history, lọc assignment active), project đang làm PM kèm tiến độ nếu có (`GET /employees/{id}/managed-projects`, thường rỗng với đa số employee thường), đơn nghỉ phép + gia hạn task đang chờ duyệt của chính mình.
- **Manager**: số nhân viên phòng ban theo status, project đang làm PM kèm tiến độ (`GET /employees/{id}/managed-projects`, cùng endpoint với employee), đơn nghỉ phép/gia hạn task đang chờ họ duyệt.
- **Admin**: tổng quan hệ thống (số phòng ban/nhân viên/project theo status), đơn từ đang chờ xử lý toàn hệ thống (leave requests + task delay requests pending).

Danh sách widget cụ thể (field nào, sắp xếp ra sao, top bao nhiêu dòng...) cần chốt kỹ hơn khi vào code Giai đoạn 2 — giống cách các phase D-I ở mục 7.3 vẫn để "cần thảo luận thêm" cho tới khi làm tới nơi.

### 9.3. Ghi chú triển khai Giai đoạn 1 — Sidebar layout (đã xong 2026-08-10)

Phạm vi đúng như 9.1: `Sidebar.jsx` (mới) thay `Header.jsx` (đã xoá), `Layout.jsx` đổi sang flex sidebar + main content, `role-redirect.js` đổi `headerNavItems()` → `sidebarNavItems()` (thêm field `icon` cho từng mục, giữ nguyên logic phân quyền theo role). Cài `@heroicons/react` (outline set). **Chưa đổi `roleHomePath()`/route `/dashboard`** — cố ý để nguyên (`/departments`, `/departments/:slug`, `/me`) vì trang Dashboard thật chưa tồn tại (Giai đoạn 2), đổi redirect bây giờ sẽ vỡ luồng đăng nhập.

**Phát sinh khi code, không lường trước lúc thảo luận:**
- **Dropdown `NotificationBell` mở lệch hẳn ra ngoài màn hình bên trái** — component này viết từ thời còn ở header (góc phải), dùng `absolute right-0 mt-2`; khi chuyển vào sidebar hẹp bên trái, `right-0` khiến dropdown 320px kéo dài về bên trái, âm ra ngoài viewport. Đổi sang `absolute bottom-full left-0 mb-2` (neo trái, mở lên trên thay vì xuống dưới, vì bell nằm cuối sidebar sát đáy màn hình — mở xuống sẽ tràn dưới viewport). Verify lại qua `getBoundingClientRect()`: trước khi sửa `x` âm (~-270), sau khi sửa nằm gọn trong khung hình.
- Toggle thu/mở dùng `title` attribute cho nav link khi collapsed (thay label ẩn) — vừa làm tooltip native, vừa giữ accessible name cho screen reader thay vì chỉ còn icon trơn.
- State collapse lưu `localStorage` key `sidebar:collapsed`, là preference theo trình duyệt (không theo user) — đúng tinh thần 9.1, không cần đổi.

**Verify:** không chụp được screenshot trực quan trong phiên này (Browser pane không hiển thị phía client), verify bằng `read_page`/`getBoundingClientRect`/`textContent` qua DOM thay thế — xác nhận cả 3 role (admin/manager/employee, dùng `admin@example.com`, `manager@test.com`, `employee@test.com`, mật khẩu `password`) thấy đúng nav item theo role, active-link highlight đúng route hiện tại, toggle thu/mở hoạt động và persist qua reload, logout hoạt động, dropdown thông báo hiển thị đúng vị trí sau khi sửa. `npm run lint` sạch. Không đụng code PHP nên không cần Pint/Pest.

**Việc còn lại:** cần xem qua bằng mắt thật (chụp ảnh/mở trực tiếp) khi có điều kiện, vì phiên này chỉ verify được qua DOM.

### 9.4. Ghi chú triển khai Giai đoạn 2 — Dashboard content + bonus profile link (đã xong 2026-08-11)

**Bonus phát sinh trước khi vào Giai đoạn 2 (theo yêu cầu user):** thêm nút "Hồ sơ của tôi" trong sidebar (icon `UserCircleIcon` + tên + role badge, bọc trong `NavLink` tới `/employees/{user.id}`, active-highlight khi đang ở đúng trang). **Không cần đổi backend/policy nào** — đọc `EmployeePolicy::update()` xác nhận self-edit (`employee.id === subject.id`) đã cho phép mọi role từ trước, và `EmployeeProfilePage.jsx`'s `canEdit` cũng đã có nhánh `employee.id === user.id`; admin/manager trước đó chỉ đơn giản là **không có đường dẫn** tới hồ sơ của chính mình trong nav (admin nav trỏ `/departments`, manager trỏ `/departments/:slug`), không phải do thiếu quyền. Verify qua browser xác nhận cả admin lẫn manager tự sửa hồ sơ được đúng theo phân quyền sẵn có (`canEditRestrictedFields` admin-only, `canEditStatus` admin+manager).

**Quyết định đổi so với mục 9.1 lúc thảo luận, phát sinh khi thiết kế:**
- **Bỏ ý "`GET /projects?with_counts=1`", thay bằng endpoint self-service mới `GET /employees/{employee}/managed-projects`** (mirror `work-history`, `ProjectService::getManagedByEmployee()` dùng `withCount` thay `loadCount`) — lý do đã ghi ở mục 9.1 (né N+1 + né giới hạn `ProjectPolicy::viewAny` chỉ cho manager/admin). Ability gate dùng `employees:read` (khớp sibling route `/employees/{employee}/projects`), không phải `projects:read`.
- **Widget "Dự án tôi quản lý" dùng chung 1 component (`DashboardManagedProjects.jsx`) cho cả employee lẫn manager** — tự ẩn hoàn toàn (return `null`) khi rỗng, không hiện card trống, vì đa số employee/nhiều manager không phải PM của project nào.
- **Widget "Đơn đang chờ" của manager KHÔNG gồm task delay requests** — chỉ leave requests. Lý do: `GetLeaveRequestsRequest` tự scope manager theo `department_id`, nhưng `GetTaskDelayRequestsRequest` **không** scope manager theo project họ quản lý (đúng note đã có ở mục 7.9/7.10 — "đây chỉ là danh sách read-only"), nên hiện task delay request ở đây sẽ gây hiểu lầm "đang chờ tôi" trong khi có thể không phải project của họ. Employee (tự scope theo bản thân) và admin (thấy toàn bộ, quyền xử lý toàn bộ) đều nhận đủ cả 2 loại.
- **Widget "Tổng quan hệ thống" (admin) hiện số dạng "N+" khi vượt quá 100** — cursor pagination không trả `total`, nên đếm bằng `data.length` của 1 lần gọi `per_page=100` (cùng tiền lệ `BOARD_PAGE_SIZE=100` của `TasksPanel`) và thêm dấu `+` nếu `meta.next_cursor` còn tồn tại, thay vì giả vờ đó là số chính xác.
- **Không đổi `max-w-5xl` của `Layout.jsx`** — lưới 2 cột (`lg:grid-cols-2`) vẫn đủ chỗ trong khung 1024px hiện có (mỗi cột ~480px), không cần nới rộng như đã cân nhắc lúc thảo luận mockup.

**Kiến trúc chung:** hook `useAsyncResource.js` (mới, `hooks/`) mirror `useCursorList` (loading/refreshing/error, `requestIdRef` chặn response cũ) nhưng bỏ cursor — mỗi widget gọi 1 lần riêng, `refresh()` cô lập theo từng instance. `DashboardWidgetCard.jsx` (mới) là shell dùng chung cho mọi widget (title + nút làm mới + xử lý loading/error/empty), để mỗi widget file chỉ còn lo fetch + render hàng dữ liệu riêng.

**Phát sinh khi verify qua browser:** `computer` tool's click theo toạ độ ngừng có tác dụng giữa phiên (dù không báo lỗi) — nghi do "Browser pane is not displayed" phía client (không chụp được screenshot suốt phiên này). Chuyển sang `form_input` (điền input) + `element.click()` qua `javascript_tool` (chỉ dùng để test, không phải để code) để tiếp tục verify — xác nhận đăng nhập/điều hướng/nút "Làm mới" của từng widget đều hoạt động đúng cho cả 3 role, dữ liệu hiển thị đúng dữ liệu thật trong DB dev (vd nhân viên "Test Employee" là PM thật của "Dự án Intern" hiện đúng ở widget "Dự án tôi quản lý" kèm tiến độ 1/4).

**Phát hiện ngoài phạm vi (không sửa, đã spawn task riêng):** `Timeline.jsx` (dùng bởi `WorkHistoryPanel`/`AssignmentsPanel`, không đụng tới trong đợt này) có bug `key={tick.date}` gây trùng key khi 2 tick trùng ngày — lộ ra khi verify qua trang hồ sơ nhân viên, không liên quan tới Sidebar/Dashboard.

Backend: 5 test mới (`EmployeeManagedProjectsTest.php`) + toàn bộ 405 test pass; `vendor/bin/pint --dirty` sạch. Frontend: `npm run lint` sạch.

### 9.5. Đợt polish sau khi user tự kiểm tra Giai đoạn 2 (đã xong 2026-08-11)

7 điểm user note sau khi dùng thử dashboard một vòng — chủ yếu "dashboard chỉ đếm số, chưa tương tác được" + vài chỗ chữ nghĩa/UI. Khảo sát trước khi code xác nhận: **đa số FE-only**, chỉ 1 điểm cần BE thật sự mới (mục dưới).

**Đổi "Đơn" → "Yêu cầu"** (toàn bộ ~19 chỗ liên quan leave-request: `DashboardPage`, `LeaveRequestsListPage`, `CreateLeaveRequestModal`, `DashboardPendingRequests`, `NotificationBell`, `role-redirect.js`) — thuần text, không đụng enum/status nào.

**Sidebar employee bỏ "Hồ sơ"** — an toàn vì `Sidebar.jsx` đã có sẵn link "Hồ sơ của tôi" (avatar) ở cuối sidebar cho mọi role từ 9.4, mục nav chính chỉ là trùng lặp.

**Trang mới `/my-tasks`** (chọn hướng "trang riêng" thay vì nhét filter vào `EmployeeProfilePage`, theo yêu cầu user): filter status + sort `due_date`/`created_at` + toggle tăng/giảm + checkbox "chỉ hiện quá hạn" (client-side, task không có status "overdue" thật), đọc/ghi `useSearchParams` — **route đầu tiên trong app dùng query param làm nguồn filter** (mọi trang list khác vẫn chỉ dùng `useState` cục bộ). BE thêm `sort`/`direction` optional vào `GetEmployeeTasksRequest`/`TaskService::getPaginatedForEmployee()` (mặc định giữ `orderBy('id','desc')` như cũ khi không truyền — không đổi hành vi `EmployeeTasksPanel`/`DashboardTaskSummary`). User có nhắc tính năng "Hot priority" (kiểu hotfix git) cho sau này — chưa code, chỉ thiết kế UI sort dễ thêm option mới.

**Dashboard tương tác được:**
- `DashboardTaskSummary` (employee): thêm tile "Quá hạn" (4 tile, tính client-side từ data đã fetch), cả 4 tile click → `/my-tasks?status=`/`?overdue=1`; list "upcoming" đổi từ `<li>` tĩnh sang nút mở `TaskDetailModal` ngay tại dashboard (mirror `EmployeeTasksPanel`, tái dùng nguyên modal).
- `DashboardSystemOverview` (admin): 2 tile Phòng ban/Nhân viên click được (`/departments`, `/employees`, không cần query param vì đã đúng default/không có filter); đổi call `listProjects` từ lọc sẵn `status=active` sang không lọc rồi group client-side theo `ProjectStatus` → 4 tile Dự kiến/Đang thực hiện/Hoàn thành/Đã hủy, mỗi tile click → `/projects?status=`.
- `ProjectsListPage` học đọc `status`/`manager_employee_id` ban đầu từ `useSearchParams` (seed 1 lần vào `useState`, không sync 2 chiều) — cần thêm 1 lần gọi `getEmployee(id)` khi có `manager_employee_id` trên URL để hiện tên trong ô search-select (bản thân URL chỉ có id).
- `DashboardManagedProjects`/`DashboardMyProjects`: title giờ là `<Link>` thay vì text — manager bấm "Dự án tôi quản lý" vào thẳng `/projects?manager_employee_id=`; **employee thì fallback về `/employees/{id}` (trang hồ sơ chính họ)** cho cả 2 widget, vì `/projects` route-gate + `ProjectPolicy::viewAny` chặn hẳn employee (không sửa Policy/route trong đợt này, đúng tinh thần "ít ảnh hưởng BE" user yêu cầu kiểm tra trước) — trang hồ sơ đã có `WorkHistoryPanel` hiển thị đúng lịch sử dự án nên fallback này không mất thông tin, chỉ mất khả năng "browse list lọc sẵn".

**Bỏ scrollbar ngang:**
- `Timeline.jsx`: bỏ hẳn `overflow-x-auto`/`min-w-[640px]` (nguyên nhân `WorkHistoryPanel` — bọc trong `max-w-xl`=576px — luôn bị cuộn ngang, tái hiện được chứ không phải giả thuyết), giảm cột tên mặc định `w-56`→`w-40 sm:w-48`. Thêm preset zoom (`getZoomedRange()` mới trong `lib/timeline.js` + `TimelineZoomControls` mới trong `Timeline.jsx`) — 4 nút Toàn bộ/6 tháng/3 tháng/1 tháng, canh quanh hôm nay (clamp vào range thật), tự dịch vào trong khi chạm biên thay vì cắt ngắn window. Không có pan/kéo (user chọn phương án đơn giản). Nhân tiện sửa luôn bug cũ đã biết (`key={tick.date}` trùng key khi 2 tick trùng ngày, note ở 9.4) vì đang sửa đúng 2 hàm đó và zoom làm bug này dễ lộ ra hơn — đổi key sang index.
- `TasksPanel.jsx` (Kanban): user xác nhận sửa luôn (không chỉ Timeline) — bỏ `overflow-x-auto` + `min-w-[200px]` cứng trên mỗi cột (nguyên nhân: 5×200px+gap=1048px > ~976px nội dung khả dụng trong `max-w-5xl` của `Layout.jsx`), thêm `min-w-0` để track tự co giãn vừa khít. Không đổi breakpoint `sm:grid-cols-2 lg:grid-cols-5`.

**Dashboard "sơ sài" → thêm widget mới cho theo dõi dự án hằng ngày** (user note riêng, yêu cầu nghiên cứu thêm UX sau khi thấy bản đầu quá đơn giản):
- Miễn phí BE: `DashboardMyProjects` thêm badge status dự án + `end_date` (cần `EmployeeWorkHistoryResource` thêm `project_status`/`project_end_date`, và phát hiện `ProjectAssignmentService::workHistory()` đang giới hạn cột `project:id,name,slug` — phải thêm `status,end_date` vào select thì field mới mới có data, không chỉ sửa Resource là đủ).
- BE nhỏ (mở rộng `withCount` có sẵn, không schema mới): `ProjectResource` thêm `todo_count`/`in_progress_count`/`in_review_count` cạnh `done_count`/`overdue_count` đã có; `ProjectService` tách `taskProgressCounts()` dùng chung cho `getManagedByEmployee()` (luôn bật) và `getPaginated()` — dùng cho `DashboardManagedProjects` (thanh xếp chồng 4 màu thay vì 1 thanh done/total).
- **Phát sinh ngoài dự tính lúc lập plan**: `getPaginated()` (`GET /projects` index) ban đầu định `withCount` luôn giống `getManagedByEmployee()`, nhưng có sẵn test `ProjectCrudTest::getProjects_list_doesNotIncludeTaskProgressCounts` khẳng định index **không** kèm counts — đúng quyết định cũ ở mục 7.9 ("tránh N+1 khi list nhiều project"). Sửa bằng cách thêm param optional `with_counts` (bool) vào `GetProjectsRequest`, `getPaginated()` chỉ `withCount` khi cờ này bật — giữ nguyên hành vi mặc định (test cũ pass, `ProjectsListPage` không đổi gì), widget mới tự truyền `with_counts=1` khi cần.
- BE mới thật sự (endpoint mới) — điểm "ảnh hưởng BE" đáng kể nhất đợt này: `GET /employees/{employee}/overdue-tasks` (`TaskService::getOverdueForManagedProjects()`, join qua `project.activeManagers`, mirror `getManagedByEmployee()`) → widget `DashboardOverdueManagedTasks.jsx` (employee/manager đang là PM, top 10 task quá hạn trong các dự án họ quản lý, click mở `TaskDetailModal` với `canReview` luôn true vì viewer chắc chắn là PM của đúng project đó). Test mới `EmployeeOverdueTasksTest.php` (5 test, mirror `EmployeeManagedProjectsTest.php`).
- Widget `DashboardAtRiskProjects.jsx` (admin-only): top 5 dự án active theo `overdue_count` giảm dần, tự fetch riêng `listProjects({status:'active', with_counts:1})` (độc lập với `DashboardSystemOverview`, đúng nguyên tắc "mỗi widget tự fetch" mục 9.1) rồi sort/slice client-side — không cần endpoint riêng.

**Verify qua browser** (cả 3 role, `.claude/launch.json` `laravel-backend`+`fe-vite-project` — lần này FE tự bind port 5175 vì nhiều phiên song song chiếm 5173/5174, phải trỏ tay giống tiền lệ 7.10/9.4): xác nhận sidebar employee đúng 4 mục (không còn Hồ sơ), dashboard employee 4 tile + click đúng `/my-tasks?status=X` lọc đúng, click task "upcoming" mở modal tại chỗ, dashboard admin 4 tile trạng thái dự án + "Dự án cần chú ý" (rỗng vì data test không có task quá hạn), `ProjectsListPage` nhận đúng `status`/`manager_employee_id` từ URL (kèm tên PM hiện đúng trong search-select), Timeline không còn cuộn ngang (`scrollWidth`≈`clientWidth` qua JS) + 4 nút zoom hoạt động, Kanban 5 cột không cuộn ngang (`scrollWidth`=`clientWidth`).

Backend: 410 test pass (bao gồm 5 test `EmployeeOverdueTasksTest.php` mới) trên toàn bộ suite; `vendor/bin/pint --dirty` sạch. Frontend: `npm run lint` sạch.

### 9.6. Đợt polish sau khi user tự kiểm tra giao diện Task Management + Dashboard (đã xong 2026-08-11)

6 điểm user note, cắt ngang cả track Task Management (mục 7) lẫn Sidebar/Dashboard (mục 9) nên gộp chung 1 mục thay vì tách theo track:

- **Gán employee sau khi tạo task**: `CreateTaskModal` đã cho chọn assignee lúc tạo, nhưng chưa từng có cách sửa lại sau đó (task tạo "chưa giao" thì kẹt luôn). Thêm endpoint riêng `PATCH /tasks/{task}/assign` (`AssignTaskRequest`, `TaskPolicy::assign()` mirror đúng `create()` — chỉ PM active của project, `TaskService::assign()`) thay vì nhét `assigned_to` vào `PATCH /tasks/{task}` sẵn có — vì endpoint đó được thiết kế quanh transition trạng thái (`UpdateTaskRequest` bắt buộc `status`, `TaskPolicy::update()` nhận `targetStatus` làm tham số quyết định ai được chuyển sang đâu), gán lại người không phải 1 transition nên tách hẳn theo SRP thay vì làm phình thêm ý nghĩa của `update()`. FE: `TaskDetailModal` thêm ô "Giao cho" editable (chỉ hiện khi `canReview` — tức chỉ ở `TasksPanel`, nơi duy nhất có `slug` để truyền vào `ProjectMemberSearchSelect`), chọn xong gọi API ngay không cần nút xác nhận riêng (cùng pattern `AssignmentsPanel.handleAddRole`).
- **Task đã hủy: strikethrough nhạt cho tiêu đề** — badge status vốn đã có `line-through` (mục 7.11) nhưng chỉ ở badge nhỏ, tiêu đề task chính thì không. Thêm hằng `CANCELLED_TITLE_CLASS` (`lib/task-status.js`) áp cho `<p>`/`<td>`/`<h2>` chứa tiêu đề ở cả 4 chỗ hiển thị task (`TasksPanel` Kanban card, `EmployeeTasksPanel`, `MyTasksPage`, `TaskDetailModal`) — dùng `decoration-gray-400` (nhạt hơn màu chữ mặc định) thay vì đổi màu chữ, đúng yêu cầu "chỉ thêm gạch ngang, gạch nhạt thôi" chứ không đổi gì khác.
- **`EmployeeProfilePage`/`ProjectDetailPage` tận dụng chiều ngang màn hình**: bọc 2 panel liên quan trong 1 grid 10 cột (`grid-cols-1 lg:grid-cols-10`, chỉ chia cột từ breakpoint `lg` trở lên) — hồ sơ nhân viên `col-span-4`/task được giao `col-span-6`; project info `col-span-7`/Project Manager `col-span-3`. Bỏ `mx-auto max-w-xl` cố định (576px) trên `EmployeeProfileView`/`EmployeeTasksPanel`/`WorkHistoryPanel` — đây chính là nguyên nhân 3 panel luôn xếp dọc dồn về giữa dù màn hình rộng bao nhiêu. `WorkHistoryPanel`/`AssignmentsPanel`/`TasksPanel` vẫn nằm ngoài grid, full-width riêng 1 hàng bên dưới, đúng yêu cầu "các thành phần khác giữ nguyên".
- **Sidebar employee thêm mục "Dự án"**: trang mới `MyProjectsPage.jsx` (route `/my-projects`), tái dùng đúng data đã có (`getEmployeeWorkHistory` lọc `!p.end_date`, cùng nguồn với widget `DashboardMyProjects` ở mục 9.4) dưới dạng bảng thay vì widget — vì employee không có `/projects` (route đó admin/manager-only), đây là cách duy nhất họ browse danh sách dự án đang tham gia ngoài dashboard. Không cần BE mới.
- **Trang duyệt request gộp cột Trạng thái + Hành động**: component mới `StatusDropdown.jsx` (dùng chung cho `LeaveRequestsListPage` + `TaskDelayRequestsListPage`) — badge trạng thái tự thành dropdown khi actor hiện tại có ít nhất 1 status khác được phép chuyển tới (tính qua hàm `getAvailableStatuses()` viết lại y hệt logic nút cũ, chỉ đổi hình thức hiển thị), trạng thái hiện tại luôn nằm trong list nhưng bị disable + dim. Chọn trạng thái khác → `window.confirm` chung 1 câu (`Đổi trạng thái sang "X"?`) cho **mọi** transition thay vì chỉ riêng "Hủy" như code cũ — gọi API ngay khi user xác nhận, không có nút Lưu riêng. Không đổi behavior/quyền BE nào, thuần FE gộp UI.
- **Đã audit thêm các màn hình khác** theo đúng yêu cầu user ("kiểm tra nếu có vấn đề tương tự") nhưng **không tìm thêm chỗ nào cần sửa**: không còn `mx-auto max-w-xl` nào sót lại ngoài `LoginPage` (cố ý, form đăng nhập nên hẹp); các trang list CRUD khác (`Employees`/`Departments`/`Levels`/`ProjectRoles`/`Projects`) đã dùng pattern inline-edit 1 cột Trạng thái từ đầu (Phase A), không có cặp cột Trạng thái+Hành động trùng lặp kiểu leave/delay-request nên không áp dụng `StatusDropdown`; `DepartmentDetailPage` không có panel phụ nào đứng cạnh info card để ghép hàng ngang.

**Phát sinh khi verify, đã xử lý:** `php artisan test --compact` (toàn bộ suite) phát hiện `AuthenticationTest::login_employeeCredentials_abilitiesPersisted` fail vì `AuthService.php` (đang staged, chưa commit) đã cấp `tasks:create` cho bucket `employee` nhưng test còn hardcode mảng cũ (thiếu `tasks:create`) từ quyết định 7.6-era — ban đầu tưởng nhầm đây là bug thừa cần xóa (`tasks:create` "lỡ" cấp), nhưng đúng ra đây là fix đúng chủ đích: PM được xác định qua `project_assignments` (`TaskPolicy::isProjectManager()`), không qua `position`, nên employee-là-PM-của-project cần `tasks:create` ở tầng token mới lọt qua middleware để tới được policy check thật sự (xem quyết định đã sửa lại ở mục 7.9 phía trên). Đã giữ nguyên `tasks:create` trong bucket `employee`, cập nhật lại test cho khớp, thêm `TaskCrudTest::createTask_employeeAsProjectManager_created` để khóa case employee-position-là-PM. Toàn bộ suite (415 test) pass; `vendor/bin/pint --dirty` sạch.

**Verify qua browser** (`laravel-backend` đã chạy sẵn port 8000 từ phiên trước đó, FE tự bind port 5175 do 5173/5174 đã bị chiếm — lặp lại tình huống 7.10/9.5): gán assignee cho task "chưa giao" qua `TaskDetailModal` (admin) → xác nhận `assigned_to` đổi đúng trong DB; task "Đã hủy" hiện `line-through` (`getComputedStyle` xác nhận) ở cả Kanban card lẫn modal; `ProjectDetailPage`/`EmployeeProfilePage` xác nhận layout 2 cột ở viewport 1400px và tự xếp dọc dưới breakpoint `lg` (test ở viewport mặc định 730px của Browser pane); đăng nhập 1 tài khoản `position=employee` thật, xác nhận sidebar có mục "Dự án" → `/my-projects` hiện đúng danh sách; `LeaveRequestsListPage`/`TaskDelayRequestsListPage` xác nhận dropdown hiện đúng option theo role (admin: cả 3-4 status; employee: chỉ Hủy khi là chủ request), chọn status khác kích `window.confirm` đúng nội dung rồi gọi API thành công (stub `window.confirm` trả `true` để verify không cần thao tác dialog thật).

Backend: 18/18 test `TaskCrudTest.php` (bao gồm 4 test mới cho `/tasks/{task}/assign`) pass; `vendor/bin/pint --dirty` sạch. Frontend: `npm run lint` và `npm run build` sạch.

### 7.12. Siết quyền task detail/comment + chặn reassign trực tiếp (đã xong 2026-08-11)

2 điểm user note sau khi tự test qua browser, đã thảo luận và chốt trước khi code (đảo ngược 1 quyết định đã document ở mục 5/7.11 và README, không phải bugfix thuần):

- **`TaskPolicy::view()`/`comment()` bỏ hẳn nhánh "plain project member"** — trước đó 1 employee chỉ có `project_assignment` trên project (không phải assignee, không phải PM) vẫn xem được task detail + comment của **mọi** task trong project (mirror `ProjectPolicy::view`, đã document rõ ở README dòng 71 cũ). Chốt lại: project member vẫn xem được **toàn bộ field task** qua list/kanban (`GET /projects/{project}/tasks` gate ở tầng `ProjectPolicy::view`, không đổi — `TaskResource` vốn đã trả đủ field cho list) nhưng chỉ **mở được detail/đọc-viết comment** nếu là chính assignee hoặc PM active của project đó (hoặc admin). Do `TaskResource` dùng chung cho list lẫn detail nên phần dữ liệu thực sự bị khoá lại chỉ là comment thread + delay-request history (2 sub-resource nằm sau `Gate::authorize('view', $task)`), không phải field nào mới của Task. `TaskDelayRequestPolicy` không đổi gì — nó vốn đã đúng hướng này từ đầu (chỉ PM hoặc requester), là data point xác nhận `TaskPolicy` trước đó bị lệch pattern so với chính policy cạnh nó.
- **`TaskPolicy::assign()` thêm rule chặn tái gán trực tiếp (A→B)** — trước đó PM đổi `assigned_to` từ người này sang người khác trong 1 lần gọi thoải mái (chủ đích ở mục 9.6). Chốt: `null→X` (gán lần đầu) và `X→null` (gỡ) luôn được phép; `A→B` trực tiếp khi task đã có assignee bị chặn — PM phải gỡ rồi gán lại như 2 lệnh riêng. Chỉ ràng buộc PM (đặt ở `TaskPolicy`, không phải `TaskService`) — admin vẫn bypass qua `before()` như mọi business-state check khác trong `TaskPolicy`/`TaskDelayRequestPolicy`, giữ đúng convention "admin là superuser" đã dùng xuyên suốt. **Không cần sửa FE**: `ProjectMemberSearchSelect` (dùng cho ô "Giao cho" trong `TaskDetailModal`) vốn đã tự nhiên 2 bước — khi `value` có giá trị, component chỉ render tên hiện tại + nút gỡ (`×`), không render ô tìm-chọn; ô tìm-chọn người mới chỉ xuất hiện sau khi `value` về `null` — tức là FE chưa từng có đường nào gọi thẳng A→B trong 1 request, khớp sẵn quyết định BE.
- **Không làm "eager load gộp"** (câu hỏi phụ user nêu cùng lúc): gộp task+comments+delay-requests vào 1 response, hoặc gộp managers+members+tasks vào `GET /projects/{slug}` — quyết định **không làm**, giữ nguyên endpoint tách riêng theo SRP (đúng lý do đã ghi ở mục 5 cho `/assign`) và cursor pagination độc lập từng list (gộp chỉ lợi được đúng lần fetch đầu, các trang sau vẫn phải gọi endpoint riêng, đổi lại là response phình to + phức tạp hoá cache-invalidation). Điểm "sửa rẻ" duy nhất đáng làm (tránh watefall `project` → rồi mới tới `AssignmentsPanel`/`TasksPanel`) hoá ra **đã có sẵn trong code** — `ProjectDetailPage.jsx` truyền thẳng `slug` (biết ngay từ `useParams()`) cho 2 panel đó thay vì đợi `project` resolve, đã có comment giải thích rõ tại chỗ.

**Test cập nhật:** `TaskAuthorizationTest::getTask_employeeProjectMemberNotAssigned_forbidden` (đổi từ `_viewable`), `TaskCommentTest::createComment_projectMemberNotAssignee_forbidden` (mới), `TaskCrudTest`: `assignTask_directReassignToDifferentEmployee_forbidden`, `assignTask_reassignToSameEmployee_allowed`, `assignTask_asAdmin_directReassignAllowed` (3 test mới). Toàn bộ suite backend (421 test) pass; `vendor/bin/pint --dirty` sạch. README bảng phân quyền (dòng 71-72) cập nhật khớp hành vi mới. Chưa verify lại qua browser sau đợt sửa này.
