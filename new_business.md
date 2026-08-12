Dựa trên phạm vi mới, giai đoạn đầu chỉ nên tập trung tạo một **vertical slice hoàn chỉnh**:

```text
Employee
→ thuộc Department
→ tham gia Project
→ có một hoặc nhiều Project Role theo từng giai đoạn
→ tạo yêu cầu thay đổi
→ Manager/Admin phê duyệt
→ hệ thống áp dụng thay đổi
→ dữ liệu quá trình làm việc được bảo toàn
```

Hai module nghiệp vụ chính sẽ là:

1. **Organization & Project Workforce**
2. **Approval Workflow**

Các phần Recruitment, Onboarding, Audit Trail đầy đủ, Google Calendar và Reporting sẽ chưa triển khai. Điều này phù hợp với phạm vi ban đầu trong file: ưu tiên quản lý phòng ban, dự án, vai trò, quá trình làm việc và yêu cầu phê duyệt. 

---

# 1. Mục tiêu của phiên bản đầu tiên

Phiên bản đầu phải trả lời được các câu hỏi nghiệp vụ sau:

* Nhân viên thuộc phòng ban nào?
* Nhân viên đang tham gia những dự án nào?
* Trong mỗi dự án, nhân viên đang giữ vai trò gì?
* Một người có thể có nhiều vai trò trong cùng dự án không?
* Nhân viên từng giữ những vai trò nào trong quá khứ?
* Ai có quyền yêu cầu thay đổi project, role, level?
* Yêu cầu cần qua những bước phê duyệt nào?
* Khi được approve, thay đổi được áp dụng vào dữ liệu như thế nào?
* Làm sao tránh việc một request được approve hai lần?
* Làm sao giữ được lịch sử mà không cần triển khai audit system đầy đủ?

## Các workflow MVP

Nên triển khai bốn workflow:

```text
Project Assignment Request
Project Transfer Request
Project Role Change Request
Level Promotion Request
```

Có thể thêm `Salary Adjustment Request` sau khi bốn workflow trên ổn định, vì lương yêu cầu bảo mật và authorization phức tạp hơn.

Không triển khai Department Transfer trong phiên bản này, đúng theo ghi chú của bạn: nhân viên có thể giữ nguyên department và chỉ chuyển project. 

---

# 2. Kiến trúc tổng thể sau khi thu gọn

```text
React SPA
    │
    ▼
Laravel REST API
    │
    ├── Identity
    ├── Organization
    ├── Workforce
    ├── Project Assignment
    ├── Approval
    └── Notification
    │
    ├── Database
    ├── Redis Queue
    └── WebSocket / Database Notification
```

## Các module

```text
Identity
├── Authentication
├── System roles
├── Permissions
└── Resource policies

Organization
├── Departments
├── Levels
└── Employees

Project Assignment
├── Projects
├── Project roles
├── Employee assignments
└── Assignment role periods

Approval
├── Approval requests
├── Approval steps
├── Approval actions
├── Workflow definitions
└── Apply handlers

Notification
├── Database notification
├── WebSocket
└── Optional email
```

`Notification` là module hỗ trợ, không phải module business trung tâm.

Audit Trail đầy đủ được hoãn lại. Giai đoạn này chỉ cần structured application log và các bảng nghiệp vụ có thời gian bắt đầu/kết thúc.

---

# 3. Boundary và dependency giữa các module

```text
Identity
    ↑
Organization
    ↑
Project Assignment
    ↑
Approval

Approval ─────→ Notification
```

Ý nghĩa:

* `Identity` quản lý user, role hệ thống và quyền.
* `Organization` quản lý employee, department, level.
* `Project Assignment` tham chiếu employee, nhưng sở hữu project và lịch sử role.
* `Approval` không tự sửa trực tiếp bảng của module khác; nó gọi contract/application service được module đích cung cấp.
* `Notification` phản ứng với các application event.

## Quy tắc quan trọng

Approval không nên làm:

```php
ProjectAssignment::query()
    ->where(...)
    ->update(...);
```

Approval nên gọi contract:

```php
interface RoleChangeApplier
{
    public function applyApprovedRoleChange(
        ApprovedRoleChangeData $data
    ): void;
}
```

`Project Assignment` implement contract này.

Nhờ vậy:

* Approval chỉ quản lý workflow.
* Assignment quản lý invariant của assignment.
* Không đặt business logic của Assignment vào Approval.
* Có thể test từng module độc lập hơn.

---

# 4. Cấu trúc Modular Monolith

```text
app/
├── Modules/
│   ├── Identity/
│   ├── Organization/
│   ├── Assignment/
│   ├── Approval/
│   └── Notification/
│
└── Shared/
    ├── Domain/
    ├── Application/
    ├── Infrastructure/
    └── Support/
```

Cấu trúc mỗi module:

```text
Assignment/
├── Application/
│   ├── Actions/
│   ├── Commands/
│   ├── Queries/
│   ├── DTOs/
│   ├── Contracts/
│   └── Events/
│
├── Domain/
│   ├── Models/
│   ├── Enums/
│   ├── ValueObjects/
│   ├── Services/
│   ├── Rules/
│   └── Exceptions/
│
├── Infrastructure/
│   ├── Persistence/
│   ├── Listeners/
│   └── Providers/
│
└── Presentation/
    └── Http/
        ├── Controllers/
        ├── Requests/
        ├── Resources/
        └── Routes/
```

Không cần tạo đủ mọi folder ngay lập tức. Chỉ tạo khi có code tương ứng.

Mục tiêu là controller mỏng, business logic không nằm trong controller, model không chứa mọi thứ và module có boundary rõ ràng. Đây cũng là định hướng được nêu trong file. 

---

# 5. Module Identity

## 5.1 System roles

Theo ghi chú của bạn, chỉ sử dụng ba role hệ thống:

```text
admin
manager
employee
```

Điều này hợp lý cho phiên bản đầu. Tech Lead, PM, Backend, QA là **project role**, không phải system role. 

## 5.2 Ý nghĩa từng role

### Admin

* CRUD department.
* CRUD level.
* CRUD employee.
* CRUD project và project role.
* gán manager cho project.
* approve bước cuối của promotion.
* xem tất cả approval request.
* cấu hình dữ liệu hệ thống.

### Manager

* xem các employee trong phạm vi mình quản lý.
* thêm nhân viên vào project nếu được cho phép.
* tạo request thay đổi role hoặc chuyển project.
* approve request thuộc project mình quản lý.
* không tự nâng mình hoặc người khác lên level mới nếu chưa qua admin.

### Employee

* xem thông tin cá nhân.
* xem các assignment và role hiện tại.
* tạo một số request được cho phép.
* theo dõi trạng thái request.
* không approve request của chính mình.

## 5.3 Permission

Vẫn nên dùng permission thay vì chỉ kiểm tra role:

```text
department.view
department.manage

employee.view
employee.manage

project.view
project.manage
project.member.manage

approval.create
approval.view
approval.approve
approval.admin-approve

level-promotion.request
level-promotion.approve-manager
level-promotion.approve-admin
```

Role chỉ là bộ permission mặc định.

## 5.4 Authorization theo hai tầng

```text
Permission
+
Policy theo resource
```

Ví dụ manager có `approval.approve`, nhưng chỉ được approve request của project mà họ quản lý.

```php
final class ApprovalRequestPolicy
{
    public function approve(
        User $user,
        ApprovalRequest $approval
    ): bool {
        if (!$user->can('approval.approve')) {
            return false;
        }

        if ($approval->requested_by === $user->id) {
            return false;
        }

        return $this->approvalScope->canApprove(
            actor: $user,
            approval: $approval,
        );
    }
}
```

## Mistakes cần tránh

* `if ($user->role === 'manager')` xuất hiện khắp code.
* Cho tất cả manager approve mọi request.
* Dùng project role để authorize hệ thống.
* Chỉ ẩn button ở React mà không có policy backend.
* Cho client gửi `approved_by`, `current_step` hoặc `status`.

---

# 6. Module Organization

Module này trong phase đầu chỉ cần:

```text
Department
Level
Employee
```

## 6.1 Department

Theo ghi chú:

* Không cần `company_id`.
* Không cần `parent_id`.
* Không cần hierarchy.
* Không cần cả `code` và `slug` nếu ý nghĩa trùng nhau. 

### Bảng departments

```text
id
name
slug
description nullable
manager_employee_id nullable
status
created_at
updated_at
```

Có thể bỏ `manager_employee_id` nếu manager chỉ quản lý project, không quản lý department. Tuy nhiên, nếu cần phân biệt department manager thì vẫn nên giữ. (Hoàng Notes: ở employee có trường dept_id rồi)

### Business rules

* `name` unique tương đối.
* `slug` unique.
* Không xóa department đang có employee.
* Có thể chuyển sang `INACTIVE` thay vì delete.
* Manager của department phải là employee active.
* Employee không được thuộc department inactive.

### Department status

```text
ACTIVE
INACTIVE
```

Không cần lifecycle phức tạp hơn.

---

# 7. Level thay cho Position

Bạn muốn đổi `positions` thành `levels`, ví dụ:

```text
Intern
Fresher
Probation
Junior
Middle
Senior
Lead
```

Điều này phù hợp nếu mục tiêu là thể hiện cấp độ nghề nghiệp thay vì chức danh.

Tuy nhiên cần lưu ý:

```text
Level ≠ Project Role
```

Ví dụ:

```text
Employee level: Senior
Project role: Backend Developer
Project role khác: Tech Lead
```

Một Senior có thể là Backend ở project A và Tech Lead ở project B.

## 7.1 Bảng levels

```text
id
name
slug
rank
probation_salary_percentage nullable
status
created_at
updated_at
```

Ví dụ:

```text
Intern      rank 10
Fresher     rank 20
Probation   rank 30
Junior      rank 40
Middle      rank 50
Senior      rank 60
Lead        rank 70
```

`rank` giúp so sánh progression mà không phụ thuộc ID.

## 7.2 Probation 80% salary

Không nên hardcode `0.8` trong code.

Có hai hướng:

### Hướng đơn giản

```text
levels.probation_salary_percentage = 80
```

Phù hợp nếu tỷ lệ thuộc chính level.

### Hướng config

```php
'probation_salary_percentage' => env(
    'PROBATION_SALARY_PERCENTAGE',
    80
),
```

Phù hợp nếu toàn hệ thống chỉ có một rule cố định.

Với project hiện tại, mình nghiêng về lưu ở `levels`, vì sau này có thể:

```text
Intern: 85%
Probation: 80%
Contractor: không áp dụng
```

## 7.3 Employee

```text
employees
```

```text
id
user_id nullable
employee_code
full_name
email
department_id
current_level_id
manager_employee_id nullable
status
joined_at
created_at
updated_at
```

### Employee status MVP

```text
ACTIVE
INACTIVE
RESIGNED
```

Không cần Onboarding, Probation, Suspended, Archived nếu nghiệp vụ đó chưa được xây.

`Probation` hiện đang là level theo ghi chú của bạn. Tuy vậy, về dài hạn probation thường là employment state hơn là career level. Trong MVP có thể giữ theo thiết kế của bạn, nhưng nên ghi lại đây là quyết định tạm thời để sau này refactor nếu Recruitment/Onboarding được thêm vào.

---

# 8. Module Project Assignment

Đây là trung tâm của phần “quá trình làm việc”.

## 8.1 Entity

```text
projects
project_roles
project_managers
project_assignments
assignment_role_periods
```

Không cần `assignment_histories` vì `project_assignments` và `assignment_role_periods` đã là temporal business data.

## 8.2 Projects

```text
id
name
slug
description nullable
status
start_date nullable
end_date nullable
created_at
updated_at
```

### Project status

```text
PLANNED
ACTIVE
COMPLETED
CANCELLED
```

### Business rules

* Không thêm member vào project `COMPLETED` hoặc `CANCELLED`.
* Không tạo assignment bắt đầu sau ngày project kết thúc.
* Không kết thúc project nếu còn active assignment, hoặc phải tự động close chúng theo rule rõ ràng.
* Project manager phải là employee active.

---

# 9. Project roles

```text
id
name
slug
description nullable
status
```

Dữ liệu ban đầu:

```text
Frontend
Backend
DevOps
QA
QC
BrSE
BA
Tech Lead
Project Manager
```

Role không nên hardcode bằng enum vì admin có thể muốn thêm:

```text
Data Engineer
Product Owner
Scrum Master
Solution Architect
```

Nên dùng bảng database.

---

# 10. Project manager

Không nên mặc định lấy system role `manager` làm project manager.

Một user có thể có system role `manager`, nhưng chỉ quản lý một số project.

Bảng:

```text
project_managers
```

```text
id
project_id
employee_id
start_date
end_date nullable
created_at
```

Cấu trúc này tốt hơn `projects.manager_id` nếu một project có nhiều manager hoặc thay manager theo thời gian.

Nếu muốn đơn giản hơn trong MVP, có thể dùng:

```text
projects.manager_employee_id
```

Nhưng bảng `project_managers` phù hợp hơn với mục tiêu có lịch sử quá trình làm việc.

---

# 11. Project assignment

Assignment thể hiện:

> Employee tham gia project nào và từ thời điểm nào đến thời điểm nào.

```text
project_assignments
```

```text
id
employee_id
project_id
start_date
end_date nullable
status
created_by
created_at
updated_at
```

### Status

```text
PENDING
ACTIVE
ENDED
CANCELLED
```

Nếu mọi assignment đều cần approval trước khi active, dùng `PENDING`.

Nếu chỉ project transfer và role change cần approval, assignment do admin tạo có thể vào `ACTIVE` trực tiếp.

## Business rules

* Một employee không được có hai active assignment cùng một project.
* `end_date >= start_date`.
* Project phải đang active.
* Employee phải active.
* Không hard delete assignment đã từng active.
* Khi kết thúc assignment, tất cả role period đang active phải kết thúc cùng ngày hoặc trước đó.

Không dùng allocation percentage, đúng theo ghi chú của bạn. 

Một nhân viên vẫn có thể tham gia nhiều project cùng lúc, nhưng hệ thống không biểu diễn phần trăm thời gian.

---

# 12. Assignment role periods

Đây là bảng giữ role hiện tại và role history.

```text
assignment_role_periods
```

```text
id
project_assignment_id
project_role_id
start_date
end_date nullable
source_approval_request_id nullable
created_at
updated_at
```

Ví dụ:

```text
ERP Project
01/01 → 30/06: Backend
01/07 → 31/12: Tech Lead
```

## Một employee có nhiều role cùng lúc

Bạn yêu cầu một người có thể có nhiều role. Vì vậy cần cho phép:

```text
ERP Project
Backend: 01/01 → hiện tại
Tech Lead: 01/07 → hiện tại
```

Không được giả định rằng role mới luôn thay thế role cũ.

Role Change Request cần có thêm `change_mode`:

```text
ADD
REPLACE
REMOVE
```

Ví dụ:

### ADD

```text
Backend vẫn active
Thêm Tech Lead
```

### REPLACE

```text
Kết thúc Backend
Bắt đầu Tech Lead
```

### REMOVE

```text
Kết thúc Tech Lead
Không tạo role mới
```

Đây là điểm rất quan trọng. Nếu chỉ có `from_role_id` và `to_role_id`, hệ thống ngầm hiểu mỗi người chỉ có một role.

---

# 13. Lịch sử quá trình làm việc

Ở giai đoạn này chưa cần bảng `employee_histories`.

Có thể lấy lịch sử từ:

```text
employees.current_level_id
project_assignments
assignment_role_periods
approved level promotion requests
```

Ví dụ endpoint:

```text
GET /api/employees/{employee}/work-history
```

Response:

```json
{
  "employee": {
    "id": 15,
    "name": "Nguyen Minh Hoang",
    "department": "IT",
    "current_level": "Junior"
  },
  "projects": [
    {
      "project": "ERP",
      "start_date": "2026-01-01",
      "end_date": null,
      "roles": [
        {
          "role": "Backend",
          "start_date": "2026-01-01",
          "end_date": null
        },
        {
          "role": "Tech Lead",
          "start_date": "2026-07-01",
          "end_date": null
        }
      ]
    }
  ]
}
```

Đây là read model/query tổng hợp, không phải bảng mới.

Nhận định của bạn rằng join các bảng đã đủ thông tin là đúng trong phạm vi hiện tại. Chỉ cần tạo bảng history riêng khi có event không thể biểu diễn bằng dữ liệu temporal hiện có.

---

# 14. Module Approval

Approval module quản lý:

```text
Ai yêu cầu?
Yêu cầu thay đổi gì?
Cần những ai approve?
Đang ở step nào?
Ai đã approve/reject?
Sau khi approve thì apply business change ra sao?
```

## 14.1 Thiết kế cân bằng

Dùng bảng approval generic cho workflow:

```text
approval_requests
approval_steps
approval_actions
```

Nhưng business data có bảng riêng:

```text
project_assignment_requests
project_transfer_requests
role_change_requests
level_promotion_requests
```

Không đặt toàn bộ dữ liệu trong một cột JSON. Lý do đã được nêu trong file: khó validate, khó query, không có foreign key và khó report. 

---

# 15. Approval request

```text
approval_requests
```

```text
id
requestable_type
requestable_id
workflow_type
requested_by
subject_employee_id
status
current_step_order
submitted_at
approved_at nullable
rejected_at nullable
applied_at nullable
failed_at nullable
failure_reason nullable
created_at
updated_at
```

## Status

```text
DRAFT
SUBMITTED
IN_REVIEW
APPROVED
REJECTED
CANCELLED
APPLIED
FAILED
```

Phải phân biệt:

```text
APPROVED = tất cả approver đã đồng ý
APPLIED = business change đã được áp dụng thành công
```

Sự phân biệt này đặc biệt quan trọng đối với:

* role change;
* project transfer;
* level promotion;
* thay đổi có `effective_date` trong tương lai.

File cũng nhấn mạnh không được xem `APPROVED` và `APPLIED` là cùng một trạng thái. 

---

# 16. Approval steps

```text
approval_steps
```

```text
id
approval_request_id
step_order
approver_kind
approver_employee_id nullable
required_permission nullable
status
acted_by nullable
acted_at nullable
comment nullable
created_at
updated_at
```

## Step status

```text
PENDING
ACTIVE
APPROVED
REJECTED
SKIPPED
```

Chỉ một step hoặc một nhóm step được active tại một thời điểm, tùy loại workflow.

## Approver kind

```text
DIRECT_MANAGER
PROJECT_MANAGER
SYSTEM_ADMIN
SPECIFIC_EMPLOYEE
PERMISSION
```

Không cần database workflow builder ở phase đầu.

---

# 17. Workflow definition bằng code

Ghi chú của bạn muốn thảo luận kỹ hơn phần này. Đây là hướng phù hợp nhất cho MVP.

## Contract

```php
interface ApprovalWorkflow
{
    public function type(): WorkflowType;

    /**
     * @return list<ApprovalStepDefinition>
     */
    public function steps(
        ApprovableRequest $request
    ): array;
}
```

## Role change workflow

```php
final class RoleChangeApprovalWorkflow implements ApprovalWorkflow
{
    public function type(): WorkflowType
    {
        return WorkflowType::PROJECT_ROLE_CHANGE;
    }

    public function steps(
        ApprovableRequest $request
    ): array {
        return [
            ApprovalStepDefinition::projectManager(),
        ];
    }
}
```

## Level promotion workflow

Theo business của bạn:

```text
Manager approve
→ Admin approve
→ hoàn tất
```

```php
final class LevelPromotionApprovalWorkflow implements ApprovalWorkflow
{
    public function type(): WorkflowType
    {
        return WorkflowType::LEVEL_PROMOTION;
    }

    public function steps(
        ApprovableRequest $request
    ): array {
        return [
            ApprovalStepDefinition::directManager(),
            ApprovalStepDefinition::systemAdmin(),
        ];
    }
}
```

## Project transfer workflow

```php
final class ProjectTransferApprovalWorkflow implements ApprovalWorkflow
{
    public function type(): WorkflowType
    {
        return WorkflowType::PROJECT_TRANSFER;
    }

    public function steps(
        ApprovableRequest $request
    ): array {
        return [
            ApprovalStepDefinition::sourceProjectManager(),
            ApprovalStepDefinition::targetProjectManager(),
        ];
    }
}
```

Có thể thêm admin cuối cùng nếu đó là rule business.

## Tại sao định nghĩa bằng code trước?

* Dễ đọc.
* Dễ version control.
* Dễ unit test.
* Không cần xây UI workflow builder.
* Không cho admin cấu hình ra workflow không hợp lệ.
* Phù hợp khi số loại request còn ít.

Chỉ đưa workflow definition vào database khi thực sự cần:

* từng department có chain khác nhau;
* người dùng business tự cấu hình workflow;
* số bước thay đổi thường xuyên;
* conditional branch phức tạp.

---

# 18. Template Method và Strategy Pattern

Bạn đề xuất kết hợp Template Pattern và Strategy Pattern. Đây là hướng hợp lý, nhưng mỗi pattern nên có trách nhiệm rõ ràng.

## 18.1 Template Method cho quy trình xử lý chung

Mọi approval đều đi qua flow:

```text
Validate request
→ Resolve workflow
→ Create approval request
→ Generate steps
→ Activate first step
→ Notify approver
```

Có thể dùng base action:

```php
abstract class SubmitApprovalRequestAction
{
    final public function execute(
        SubmitApprovalData $data
    ): ApprovalRequest {
        return DB::transaction(function () use ($data) {
            $businessRequest = $this->createBusinessRequest($data);

            $this->validateBusinessRules($businessRequest);

            $approval = $this->createApproval($businessRequest);

            $steps = $this->workflow()
                ->steps($businessRequest);

            $this->persistSteps($approval, $steps);
            $this->activateFirstStep($approval);

            return $approval;
        });
    }

    abstract protected function createBusinessRequest(
        SubmitApprovalData $data
    ): ApprovableRequest;

    abstract protected function validateBusinessRules(
        ApprovableRequest $request
    ): void;

    abstract protected function workflow(): ApprovalWorkflow;
}
```

Tuy nhiên, đừng ép dùng inheritance nếu nó làm code khó hiểu. Một orchestrator dùng composition thường dễ maintain hơn:

```php
final class SubmitApprovalRequestAction
{
    public function __construct(
        private WorkflowRegistry $workflows,
        private ApprovalStepFactory $stepFactory,
    ) {}
}
```

Mình ưu tiên **composition + strategy** hơn template bằng abstract class.

## 18.2 Strategy cho workflow khác nhau

```php
interface ApprovalWorkflow
{
    public function steps(
        ApprovableRequest $request
    ): array;
}
```

Mỗi loại request là một strategy.

## 18.3 Strategy cho apply handler

```php
interface ApprovedRequestHandler
{
    public function type(): WorkflowType;

    public function apply(
        ApprovalRequest $approval
    ): void;
}
```

Các implementation:

```text
ApplyProjectAssignmentHandler
ApplyProjectTransferHandler
ApplyRoleChangeHandler
ApplyLevelPromotionHandler
```

Đây đúng với hướng được ghi chú trong file. 

---

# 19. Workflow Registry

Không nên dùng `match` rải rác.

```php
final class ApprovalWorkflowRegistry
{
    /**
     * @param iterable<ApprovalWorkflow> $workflows
     */
    public function __construct(
        private iterable $workflows
    ) {}

    public function get(
        WorkflowType $type
    ): ApprovalWorkflow {
        foreach ($this->workflows as $workflow) {
            if ($workflow->type() === $type) {
                return $workflow;
            }
        }

        throw new UnsupportedWorkflowType($type);
    }
}
```

Tương tự:

```php
final class ApprovedRequestHandlerRegistry
{
    public function get(
        WorkflowType $type
    ): ApprovedRequestHandler {
        // resolve handler
    }
}
```

Bind bằng service provider:

```php
$this->app->tag([
    RoleChangeApprovalWorkflow::class,
    ProjectTransferApprovalWorkflow::class,
    LevelPromotionApprovalWorkflow::class,
], 'approval.workflows');
```

---

# 20. State machine

Bạn ghi chú mỗi request có flow khác nhau. Nên tách hai loại state:

## 20.1 Approval lifecycle chung

```text
DRAFT
→ SUBMITTED
→ IN_REVIEW
→ APPROVED
→ APPLIED
```

Nhánh khác:

```text
SUBMITTED / IN_REVIEW
→ REJECTED
```

Hoặc:

```text
DRAFT / SUBMITTED
→ CANCELLED
```

State machine chung quản lý lifecycle này.

```php
final class ApprovalStateMachine
{
    public function ensureCanApprove(
        ApprovalRequest $approval
    ): void {
        if (!in_array($approval->status, [
            ApprovalStatus::SUBMITTED,
            ApprovalStatus::IN_REVIEW,
        ], true)) {
            throw new InvalidApprovalTransition();
        }
    }

    public function ensureCanCancel(
        ApprovalRequest $approval
    ): void {
        // rules
    }
}
```

## 20.2 Business request state riêng

Ví dụ role change:

```text
PENDING_APPROVAL
APPROVED
APPLIED
REJECTED
CANCELLED
```

Không nhất thiết phải tạo State class riêng cho từng trạng thái. Enum + transition service đủ cho quy mô này, đúng theo đề xuất trong file. 

## Khi nào mới dùng State Pattern đầy đủ?

Chỉ khi mỗi state có nhiều hành vi riêng:

```text
mỗi state cho phép command khác nhau
mỗi state có validation riêng lớn
transition có nhiều side effect
```

Hiện tại chưa cần.

---

# 21. Workflow 1 — Project Assignment Request

Mục đích:

> Đề xuất thêm một employee vào project.

## Dữ liệu

```text
project_assignment_requests
```

```text
id
employee_id
project_id
proposed_start_date
reason
status
created_by
created_at
updated_at
```

Role khởi tạo có thể đặt ở bảng con:

```text
project_assignment_request_roles
```

```text
id
request_id
project_role_id
```

## Flow

```text
Manager/Admin tạo request
→ validate employee và project
→ tạo approval request
→ resolve project manager
→ project manager approve
→ approval chuyển APPROVED
→ ApplyProjectAssignmentHandler chạy
→ tạo project_assignment
→ tạo assignment_role_periods
→ approval chuyển APPLIED
→ notify employee
```

## Rules

* Employee chưa active trong project.
* Project đang active.
* Proposed date hợp lệ.
* Có ít nhất một project role.
* Approver không phải requester nếu có thể.
* Không tạo duplicate pending request cho cùng employee và project.

---

# 22. Workflow 2 — Project Role Change

## Dữ liệu

```text
role_change_requests
```

```text
id
employee_id
project_assignment_id
change_mode
from_role_id nullable
to_role_id nullable
effective_date
reason
status
created_by
created_at
updated_at
```

## Rules theo mode

### ADD

```text
from_role_id = null
to_role_id required
```

* Role mới chưa active.

### REPLACE

```text
from_role_id required
to_role_id required
```

* Role cũ đang active.
* Role mới khác role cũ.

### REMOVE

```text
from_role_id required
to_role_id = null
```

* Role cần xóa đang active.
* Không được xóa role cuối cùng nếu project yêu cầu mỗi assignment phải có ít nhất một role.

## Flow

```text
Employee hoặc Manager tạo request
→ project manager approve
→ request APPROVED
→ ApplyRoleChangeHandler
    ├── ADD: tạo role period mới
    ├── REPLACE: close old + create new
    └── REMOVE: close old
→ request APPLIED
→ notify employee
```

## Transaction

```php
DB::transaction(function () use ($approval) {
    $roleChange = $this->loadAndLockRequest($approval);

    $assignment = $this->loadAndLockAssignment(
        $roleChange->project_assignment_id
    );

    $this->roleChangeService->apply(
        assignment: $assignment,
        request: $roleChange,
    );

    $approval->markApplied();
});
```

---

# 23. Workflow 3 — Project Transfer

Project transfer không phải update `project_id` trên assignment cũ.

## Dữ liệu

```text
project_transfer_requests
```

```text
id
employee_id
source_assignment_id
target_project_id
target_start_date
source_end_date
reason
status
created_by
created_at
updated_at
```

Vai trò tại project mới:

```text
project_transfer_request_roles
```

```text
id
request_id
project_role_id
```

## Flow

```text
Request transfer
→ source project manager approve
→ target project manager approve
→ request APPROVED
→ đến effective date hoặc apply ngay
→ close source assignment
→ close active source role periods
→ create target assignment
→ create target role periods
→ request APPLIED
→ notify employee và managers
```

## Rules

* Source assignment đang active.
* Target project khác source project.
* Employee chưa active ở target project.
* Source end date trước target start date hoặc bằng ngày liền trước.
* Target project active.
* Có ít nhất một target role.

---

# 24. Workflow 4 — Level Promotion

## Dữ liệu

```text
level_promotion_requests
```

```text
id
employee_id
from_level_id
to_level_id
effective_date
reason
status
created_by
created_at
updated_at
```

## Flow

```text
Employee hoặc Manager tạo request
→ Direct Manager approve
→ Admin approve
→ request APPROVED
→ nếu effective_date <= hiện tại:
      update employee current_level_id
      request APPLIED
  nếu effective_date trong tương lai:
      chờ scheduler
→ notify employee
```

Đây đúng với note của bạn: manager approve trước, sau đó admin approve, admin approve xong mới coi là hoàn tất về mặt phê duyệt. 

Tuy nhiên, về technical status:

```text
Admin approve xong = APPROVED
Update level thành công = APPLIED
```

## Rules

* `to_level.rank > from_level.rank`.
* `from_level_id` phải khớp current level tại thời điểm submit.
* Không có pending promotion request khác.
* Requester không tự approve.
* Khi apply, phải kiểm tra current level lần nữa để tránh stale request.
* Nếu current level đã thay đổi, chuyển `FAILED` hoặc yêu cầu review lại.

---

# 25. Effective date và Scheduler

Có hai loại request:

## Apply ngay

```text
effective_date <= today
```

Sau khi step cuối approve, handler apply trong transaction.

## Apply trong tương lai

```text
effective_date > today
```

Request giữ trạng thái:

```text
APPROVED
```

Scheduler chạy mỗi ngày:

```php
Schedule::command('approvals:apply-effective-requests')
    ->dailyAt(config('approvals.apply_time', '00:05'))
    ->withoutOverlapping()
    ->onOneServer();
```

Command:

```text
find APPROVED requests
where effective_date <= today
→ dispatch unique ApplyApprovedRequestJob
```

Job phải idempotent:

* Nếu đã `APPLIED` thì return.
* Lock approval record.
* Validate lại business state.
* Apply thay đổi.
* Mark `APPLIED`.

---

# 26. Approve action chi tiết

```text
POST /api/approvals/{approval}/approve
```

Flow:

```text
Authenticate
→ Policy authorize
→ Begin transaction
→ lock approval request
→ validate state
→ load active step
→ validate actor is approver
→ ensure actor has not acted
→ record approval action
→ mark step APPROVED
→ activate next step nếu còn
→ nếu hết step:
      mark approval APPROVED
→ commit
→ notify next approver hoặc dispatch apply job
```

Pseudo-code:

```php
final class ApproveRequestAction
{
    public function execute(
        ApprovalRequestId $id,
        EmployeeId $actorId,
        ?string $comment,
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $id,
            $actorId,
            $comment
        ) {
            $approval = $this->approvals
                ->findForUpdate($id);

            $this->stateMachine
                ->ensureCanApprove($approval);

            $step = $approval->activeStepForUpdate();

            $this->approverGuard->ensureCanAct(
                actorId: $actorId,
                step: $step,
                approval: $approval,
            );

            $step->approve($actorId, $comment);

            if ($approval->hasNextStep()) {
                $approval->activateNextStep();
            } else {
                $approval->markApproved();
            }

            return $approval;
        });
    }
}
```

---

# 27. Reject và Cancel

## Reject

```text
POST /api/approvals/{id}/reject
```

Rules:

* Chỉ active approver được reject.
* Comment/reason bắt buộc.
* Mark current step `REJECTED`.
* Mark approval `REJECTED`.
* Không apply business change.
* Notify requester.

## Cancel

```text
POST /api/approvals/{id}/cancel
```

Rules:

* Requester hoặc admin.
* Chỉ cancel khi chưa `APPROVED`, `APPLIED` hoặc `REJECTED`.
* Requester không được cancel khi một số business workflow đã vào bước không thể rollback, nhưng MVP chưa có trường hợp này.

---

# 28. Notification bằng Observer/Event Pattern

Ghi chú của bạn muốn sử dụng Observer Pattern. Trong Laravel, nên thể hiện qua application events và listeners:

```text
ApprovalSubmitted
ApprovalStepActivated
ApprovalApproved
ApprovalRejected
ApprovalApplied
ProjectRoleChanged
ProjectTransferred
EmployeeLevelPromoted
```

Ví dụ:

```php
final class NotifyNextApprover
{
    public function handle(
        ApprovalStepActivated $event
    ): void {
        $event->approver->notify(
            new ApprovalWaitingForAction(
                $event->approvalId
            )
        );
    }
}
```

Flow:

```text
Application Event
→ Listener
→ Queue
→ Database Notification / WebSocket
```

Không dùng Eloquent Observer cho toàn bộ business workflow. Eloquent Observer phù hợp với technical lifecycle của model, nhưng application event rõ ý nghĩa business hơn.

## Phase đầu nên hỗ trợ

```text
Database notification
WebSocket notification
```

Email có thể để sau.

---

# 29. Không triển khai Audit module nhưng vẫn cần logging

Theo ghi chú, phase này chỉ cần logging system trước. 

## Application log

Mỗi business action log:

```text
request_id
actor_id
action
module
entity_type
entity_id
result
duration
context
timestamp
```

Ví dụ:

```json
{
  "request_id": "01J...",
  "actor_id": 15,
  "module": "approval",
  "action": "approval.approved",
  "entity_type": "role_change_request",
  "entity_id": 86,
  "result": "success"
}
```

Không log:

* password;
* access token;
* toàn bộ request body;
* salary chi tiết không cần thiết;
* thông tin cá nhân nhạy cảm.

## Business history vẫn được giữ

Không có audit table vẫn không mất lịch sử vì:

```text
project_assignments có start_date/end_date
assignment_role_periods có start_date/end_date
approval_actions lưu ai đã approve/reject
level_promotion_requests lưu thay đổi level
```

---

# 30. Database schema tổng hợp

```text
users
roles
permissions
role_user
permission_role

departments
levels
employees

projects
project_roles
project_managers
project_assignments
assignment_role_periods

approval_requests
approval_steps
approval_actions

project_assignment_requests
project_assignment_request_roles

project_transfer_requests
project_transfer_request_roles

role_change_requests

level_promotion_requests

notifications
```

## approval_actions

```text
id
approval_request_id
approval_step_id
actor_employee_id
action
comment nullable
acted_at
created_at
```

Action:

```text
SUBMITTED
APPROVED
REJECTED
CANCELLED
APPLIED
FAILED
```

`approval_steps` giữ trạng thái hiện tại của từng step.

`approval_actions` là immutable activity history của workflow.

---

# 31. Constraints quan trọng

## Departments

```text
unique(slug)
```

## Levels

```text
unique(slug)
unique(rank)
```

## Employees

```text
unique(employee_code)
unique(user_id)
foreign key department_id
foreign key current_level_id
```

## Project assignments

```text
check start_date <= end_date
```

Không thể dễ dàng tạo unique chỉ cho active assignment trong mọi database. Phải enforce bằng transaction + lock.

## Role periods

```text
check start_date <= end_date
```

Prevent duplicate active role:

```text
same assignment
same role
end_date is null
```

Validate trong service và transaction.

## Approvals

* Một approval có đúng một business request.
* Step order unique trong approval.
* Một step chỉ được acted một lần.
* Request đã applied không được apply lại.
* Không có hai pending request cùng loại tác động lên cùng resource nếu chúng xung đột.

---

# 32. API MVP

## Organization

```text
GET    /api/departments
POST   /api/departments
PATCH  /api/departments/{department}
POST   /api/departments/{department}/deactivate

GET    /api/levels
POST   /api/levels
PATCH  /api/levels/{level}

GET    /api/employees
GET    /api/employees/{employee}
POST   /api/employees
PATCH  /api/employees/{employee}
GET    /api/employees/{employee}/work-history
```

## Projects

```text
GET    /api/projects
POST   /api/projects
GET    /api/projects/{project}
PATCH  /api/projects/{project}
POST   /api/projects/{project}/complete

GET    /api/project-roles
POST   /api/project-roles

GET    /api/projects/{project}/members
GET    /api/employees/{employee}/assignments
```

## Requests

```text
POST /api/project-assignment-requests
POST /api/project-transfer-requests
POST /api/role-change-requests
POST /api/level-promotion-requests
```

## Approval

```text
GET  /api/approvals
GET  /api/approvals/{approval}

POST /api/approvals/{approval}/approve
POST /api/approvals/{approval}/reject
POST /api/approvals/{approval}/cancel
```

Không dùng endpoint generic:

```text
PATCH /api/approvals/{id}
{
  "status": "approved"
}
```

Business command endpoint giúp ngăn client đặt state tùy ý, như file đã đề xuất. 

---

# 33. React frontend

```text
src/
├── app/
├── modules/
│   ├── organization/
│   ├── employees/
│   ├── projects/
│   ├── assignments/
│   └── approvals/
└── shared/
```

## Các màn hình chính

### Organization

* Department list.
* Level list.
* Employee list.
* Employee detail.
* Employee work history.

### Project

* Project list.
* Project detail.
* Project members.
* Member role history.
* Add member request.
* Transfer project request.
* Role change request.

### Approval

* My requests.
* Pending my approval.
* Approval detail.
* Step timeline.
* Approve/reject modal.
* Admin approval queue.

## State management

* TanStack Query cho server state.
* React Hook Form + Zod cho form.
* Không đưa employee/project/approval API data vào Redux.
* Permission chỉ để điều khiển UI; backend policy vẫn quyết định thật.

---

# 34. Testing plan

## Unit tests

### Assignment

* không tạo duplicate active assignment;
* không thêm employee vào inactive project;
* role ADD hoạt động;
* role REPLACE đóng role cũ;
* role REMOVE đóng đúng period;
* không tạo duplicate active role;
* transfer đóng source và tạo target;
* date range hợp lệ.

### Approval

* workflow tạo đúng step;
* level promotion có manager → admin;
* project transfer resolve đúng hai manager;
* requester không tự approve;
* chỉ active step được approve;
* reject kết thúc workflow;
* request approved không apply hai lần;
* stale level promotion bị fail;
* concurrent approval không tạo duplicate action.

## Feature tests

```text
Manager creates role change request
→ project manager approves
→ new role period exists
→ request APPLIED
```

```text
Employee requests promotion
→ manager approves
→ admin approves
→ employee level changes
```

```text
Unauthorized manager tries to approve another project
→ 403
```

## E2E

Chỉ cần hai flow chính:

```text
Role Change Request → Approval → Role History
Level Promotion → Manager Approval → Admin Approval
```

---

# 35. Security cho hai module

## Authentication

* Sanctum cookie-based nếu React và Laravel là first-party SPA.
* CSRF protection.
* CORS allowlist.
* Session rotation sau login.

## Authorization

```text
Middleware
→ Permission
→ Policy
→ Domain rule
```

## Input validation

### FormRequest

Kiểm tra:

* required;
* type;
* enum;
* date;
* foreign key tồn tại.

### Domain rule

Kiểm tra:

* employee đang active;
* role đang active;
* assignment thuộc employee;
* manager có quyền trên project;
* transition hợp lệ;
* không có conflicting request;
* effective date hợp lệ.

### Database

* unique;
* foreign key;
* check constraint;
* transaction;
* lock.

## Concurrency

Khi approve:

```php
ApprovalRequest::query()
    ->lockForUpdate()
    ->findOrFail($id);
```

Khi apply role change, lock:

* approval request;
* business request;
* project assignment;
* relevant role periods.

File cũng xác định locking là cần thiết khi có hai người cùng thao tác approve. 

## Mass assignment

Không nhận từ client:

```text
status
current_step
approved_by
applied_at
from_level_id
from_role_id
```

Các trường `from_*` phải được backend lấy từ current state để chống tampering.

---

# 36. Thứ tự triển khai chi tiết

## Phase 0 — Foundation

* Chuẩn hóa modular structure.
* Sanctum authentication.
* Ba system roles.
* Permission và Policy base.
* Exception response format.
* Request ID middleware.
* Structured logging.
* Base React architecture.
* CI chạy lint và test.

**Kết quả:** có nền tảng bảo mật và cấu trúc module.

---

## Phase 1 — Organization Core

* Department CRUD.
* Level CRUD.
* Employee CRUD.
* Manager relationship.
* Employee current department và current level.
* Policy.
* Filter employee theo department, level, status.

**Chưa làm:** employee lifecycle phức tạp, recruitment, onboarding.

**Kết quả:** có employee thật để dùng cho project và approval.

---

## Phase 2 — Project Core

* Project CRUD.
* Project Role CRUD.
* Project Manager.
* Project detail.
* Project member list.
* Project lifecycle cơ bản.

**Kết quả:** có cấu trúc project và người quản lý project.

---

## Phase 3 — Assignment Core

* Tạo assignment trực tiếp bởi admin.
* Gắn nhiều role cho employee.
* Kết thúc assignment.
* Kết thúc role.
* Work history query.
* Validate period và duplicate active data.

**Kết quả:** biểu diễn được quá trình làm project và lịch sử role mà chưa cần approval.

Làm bước này trước Approval để tách rõ:

> Assignment service hoạt động độc lập, Approval chỉ orchestration việc gọi nó.

---

## Phase 4 — Approval Engine Core

* `approval_requests`.
* `approval_steps`.
* `approval_actions`.
* Workflow interface.
* Workflow registry.
* State machine.
* Approve/reject/cancel.
* Locking.
* Policy.
* Database notification.

Chưa cần request business phức tạp. Có thể dùng một dummy request trong test để kiểm tra engine.

**Kết quả:** approval engine chạy độc lập.

---

## Phase 5 — Role Change Vertical Slice

* `role_change_requests`.
* Role change workflow.
* Role change apply handler.
* ADD, REPLACE, REMOVE.
* WebSocket notification.
* Unit, feature, E2E test.

**Kết quả:** workflow đầu tiên hoàn chỉnh từ UI đến database.

Đây nên là milestone demo đầu tiên.

---

## Phase 6 — Level Promotion

* `level_promotion_requests`.
* Manager → Admin workflow.
* Apply ngay và future effective date.
* Scheduler.
* Conflict checking.
* Notification.

**Kết quả:** chứng minh approval engine có thể tái sử dụng cho workflow nhiều step.

---

## Phase 7 — Project Assignment Request

* Request thêm người vào project.
* Chọn nhiều role.
* Project manager approval.
* Create assignment handler.

**Kết quả:** thêm member qua quy trình kiểm soát.

---

## Phase 8 — Project Transfer

* Source assignment.
* Target project.
* Source manager và target manager.
* Close old assignment.
* Create new assignment.
* Effective date.
* Conflict validation.

**Kết quả:** quá trình chuyển dự án được bảo toàn lịch sử.

---

## Phase 9 — Hardening

* Redis queue.
* Horizon.
* Unique jobs.
* Retry.
* Rate limiting.
* Index review.
* Load test approval list.
* Security review.
* Optimistic concurrency cho form edit.
* OpenAPI documentation.
* Seed demo data.

---

# 37. Những phần chưa triển khai nhưng cần chuẩn bị extension point

## Recruitment

Sau này Recruitment chỉ cần gọi contract để tạo employee.

Không cần viết code Recruitment bây giờ.

## Onboarding

Employee có thể nhận thêm status và onboarding process sau.

Không cần thêm hàng loạt status ngay từ đầu.

## Audit

Hiện tại dùng business temporal data và structured logs.

Sau này `Audit` có thể subscribe các application event:

```text
ProjectRoleChanged
ProjectTransferred
EmployeeLevelPromoted
ApprovalActionRecorded
```

## Salary Adjustment

Sau khi Authorization và Audit ổn định mới thêm, vì salary là dữ liệu nhạy cảm.

---

# 38. Mistakes quan trọng nhất cần tránh

## Domain mistakes

* Lưu project role trực tiếp trên employee.
* Chỉ cho một role trong project.
* Update role cũ thay vì đóng period.
* Update project ID để transfer.
* Dùng audit log thay cho assignment history.
* Xem level và project role là một khái niệm.
* Xem manager system role là manager của mọi project.

## Approval mistakes

* Toàn bộ request data nằm trong JSON.
* Controller đổi status trực tiếp.
* Không phân biệt approved và applied.
* Không lock khi approve.
* Không revalidate khi apply.
* Requester tự approve.
* Hardcode step logic trong controller.
* Xây dynamic workflow builder quá sớm.
* Dùng event async cho thay đổi bắt buộc phải atomic.

## Architecture mistakes

* Repository cho mọi CRUD.
* Tạo interface không có khả năng thay đổi implementation.
* Shared module trở thành nơi ném mọi helper.
* Module Approval truy cập trực tiếp model nội bộ Assignment.
* Dùng event cho mọi method call.
* Tách microservice trước khi monolith ổn định.

---

# 39. Definition of Done cho mỗi workflow

Ví dụ Role Change chỉ được xem là hoàn tất khi có:

```text
Migration và constraint
Business request model
DTO
FormRequest
Policy
Workflow strategy
Approver resolution
Approval steps
Approve/reject/cancel
Apply handler
Transaction và locking
Idempotency
Notification
Structured log
Unit test
Feature test
E2E critical path
API documentation
```

Approve không chỉ là đổi status. Theo nội dung trong file, nó còn phải validate actor, lock request, validate active step, record action, advance workflow, apply business change và hỗ trợ idempotency. 

---

# 40. Kiến trúc mục tiêu của phiên bản đầu

```text
Identity
    │
    ▼
Organization
    │
    ▼
Project Assignment
    │
    ▼
Approval
    │
    ▼
Notification
```

Luồng ghi dữ liệu:

```text
Controller
→ FormRequest
→ Action
→ Domain rule
→ Transaction
→ Model/Repository
```

Luồng phê duyệt:

```text
Business Request
→ Workflow Strategy
→ Approval Steps
→ Approve/Reject
→ Approved Request Handler
→ Assignment/Employee change
→ Notification
```

Luồng lịch sử:

```text
Current employee data
+
Project assignments
+
Assignment role periods
+
Applied approval requests
→ Employee Work History Read Model
```

Với phạm vi hiện tại, milestone tốt nhất để trình bày với Tech Lead là:

> Một employee đang giữ role Backend trong project ERP tạo yêu cầu thêm role Tech Lead. Project Manager phê duyệt, hệ thống tạo role period mới mà không làm mất role Backend, chuyển request từ `APPROVED` sang `APPLIED`, gửi notification và thể hiện đầy đủ lịch sử làm việc.

Workflow này vừa thể hiện đúng business của bạn, vừa chứng minh được modular monolith, temporal data, reusable approval workflow, Strategy Pattern, state transition, transaction, locking, authorization và realtime notification.
