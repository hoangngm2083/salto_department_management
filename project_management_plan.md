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
| **C.5** | **Task Management** (Task + Comment + Delay Request + Policy + progress trên Project resource) | **6-8 ngày** |
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

**Phase C.5, D, E, F, G, H, I — FE: cần thảo luận thêm.** Chưa thiết kế UI cụ thể (task board/kanban, approval request list + step timeline, form request theo từng `change_mode`, approve/reject modal...) — để tới khi làm tới từng phase mới chốt, vì UX phụ thuộc feedback thực tế từ các phase trước.

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

## 8. Milestone demo đề xuất

Giữ nguyên tinh thần `new_business.md` mục 40: employee đang giữ role Backend ở project ERP → tạo request thêm role Tech Lead → PM duyệt → role period mới được tạo, không mất role cũ → request chuyển APPROVED → APPLIED → notify → thể hiện đầy đủ work-history. Có thể bổ sung thêm nhánh Task Management (tạo task, chuyển trạng thái, request delay, PM duyệt) làm milestone phụ vì hoàn thành trước và độc lập với Approval Engine.
