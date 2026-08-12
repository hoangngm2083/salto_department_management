# Authorization & Business Rules

Quick reference for who can do what, per resource. Role = `Employee.position`: `admin` / `manager` / `employee`. Rows not listed for a role mean "no access to that action."

## Employees

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | All | Own department only | Self only |
| Create | Yes, any department | Own department only (department auto-assigned, can't be changed) | No |
| Update | Yes, any field | Own department only; cannot change role/department | Self only; cannot change role/department |
| Delete | Yes | No | No |
| Import / Export | Yes | No | No |

**Extra fields**
- `current_level_id`, `manager_employee_id` — admin-only to set/change.
- `status` (`active` / `inactive` / `resigned`) — admin and manager can both set/change (manager limited to own department); employee cannot change their own status.
- Setting `status → resigned` auto-closes (`end_date = today`) every active `project_assignment` and `assignment_role_period` for that employee.
- Deleting or resigning an employee is **blocked (422)** if they are the sole active manager of any project — a replacement manager must be assigned first.

## Departments

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | All | Own department only | No access |
| Create | Yes | No | No |
| Update | Yes | Own department only | No |
| Delete | Yes | No | No |
| Import / Export | Yes | No | No |

## Levels

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | Yes | Yes | No access |
| Create | Yes | No | No |
| Update | Yes | No | No |
| Delete | Yes | No | No |

- Rank is derived automatically on create (`insert_position`: `start`/`end`/`before`/`after` + `reference_level_id`) — not passed directly.
- Rank can only be edited directly via inline update, never on create.

## Projects

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | All | All, unconditionally | Only projects they are (or were) the PM of, or currently have (or previously had) an assignment on — cannot browse the list |
| Create | Yes (requires ≥ 1 PM up front) | No | No |
| Update / Delete | Yes | No | No |
| Add / remove PM | Yes | No — admin-only, even for a project's own PM | No |

- A project must always have **≥ 1 active PM**. Any action that would remove the last one is blocked (422) unless a replacement PM is provided in the same request.

## Project Roles

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | Yes | Yes | No access |
| Create / Update / Delete | Yes | No | No |

## Project Assignments & Role Periods

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | All | All | No direct access (visible via the project itself) |
| Add / end assignment or role period | Any project | Only projects where they're currently an active PM | No |

- An employee can hold multiple roles at once on the same project (multiple concurrent role periods).
- Ending an assignment also ends every still-active role period on it.
- Assigning requires the project to be `planned` or `active` (not `completed`/`cancelled`) and the assignee to be an active employee.

## Project Members Search — `GET /projects/{project}/members`

Same access rule as viewing the project itself. Returns only employees with an **active** assignment on that project.

## Tasks

| Action | Admin | Manager | Employee |
|---|---|---|---|
| View | All | All | Tasks assigned to them, on a project they are (or were) the PM of, or on a project they have (or had) an assignment on |
| Create / assign | Any project | Only projects where they're currently an active PM | No |
| `todo → in_progress → in_review` | Yes | Yes, if PM | Yes, only if they're the assignee |
| Approve (`in_review → done`) / reject (`→ in_progress`) | Yes | Only if PM of that project | No — never their own task |
| Cancel | Yes | Only if PM of that project | No |

- A task can never be cancelled once `done`.
- The assignee can never approve/reject their own `in_review` task.

## Task Comments

Same viewing rule as the underlying task — anyone who can view a task can comment on it.

## Task Delay Requests

| Action | Admin | Manager | Employee |
|---|---|---|---|
| Create | — | — | Only the task's own assignee |
| Approve / reject | Yes | Only if PM of that project | No |
| Cancel | Yes | No | Only their own pending request |
| List | All | All (not scoped to their own projects) | Own requests only |

- Requires: the task already has a due date, isn't `done`/`cancelled`, has no other `pending` request, and the requested date is after the task's current due date.
- Approving a request immediately updates `tasks.due_date` — there's no separate "applied" state.
