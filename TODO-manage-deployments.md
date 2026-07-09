# Manage Deployments Follow-ups

- Keep the Moodle Crucible and TopoMojo manage deployments pages aligned for teacher-managed lab operations.
- Teachers may need to extend selected students' active labs from the manage deployments page, even when students should not extend labs themselves.
- Review whether student-facing extend controls should become independently configurable from teacher-facing manage page extension controls.

## Crucible Group Mode

Use Alloy's existing create-for-user endpoint for the primary group owner, then use the admin-enlist endpoint for additional group members.

Planned behavior:

- Add an activity-level group mode setting for teacher-managed Crucible deployments.
- When deploying for a Moodle group, choose one Moodle user as the Alloy event owner. Prefer an explicit leader if the UI supports it; otherwise use a deterministic first eligible group member.
- Launch the Alloy event with the owner user's Alloy GUID and display name so Alloy sets `CreatedBy`, `UserId`, and the event Manager membership to that user.
- After Alloy reports the event as active, call the admin-enlist endpoint for the remaining group members so they are added to Alloy, Player, and Steamfitter without needing an invite code.
- Create or link Moodle attempt records so every group member can open the shared lab from the activity page while the owner remains the only Alloy invite/share-code owner.
- Keep instructor controls in Moodle. Do not make the teacher the Alloy owner unless the teacher is intentionally deploying a teacher-owned lab.

Implementation notes:

- Store enough Moodle-side relationship data to identify a shared group deployment, the owner user, the member users, and the shared Alloy event id.
- Prevent duplicate active deployments for the same group/activity pair.
- Decide how grading should be applied: one shared group grade, copied individual grades, or owner-only grade with clear UI wording.
- Preserve the existing individual deploy-now behavior as the default.

Test coverage:

- Unit-test the group deployment selection logic, including deterministic owner selection and duplicate prevention.
- Mock Alloy calls to verify the owner is passed to event creation and non-owner members are sent to admin-enlist only after the event is active.
- Add Playwright coverage for instructor group deploy UI and learner access for owner and non-owner group members.
