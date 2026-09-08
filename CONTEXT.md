# CONTEXT — Medication Manager domain glossary

Canonical terms agreed on the day the idea was shaped. Update this file only
when a term's meaning is challenged or a new one crystallises. No
implementation detail lives here.

- medication   A drug given to a person. The thing the schedule and stock
               both talk about.
- package      A supply unit of a medication (one blister, one box). Has
               stock that can run out (e.g. N doses left). Refill forecasting
               is about packages.
- intake       A single scheduled dose of a medication at a time. The atom of
               the schedule.
- schedule     The ordered set of intakes for a medication across a day.
- shift        The re-timing of remaining intakes after a dose is taken late
               (the core v1 behavior).
- home inventory  The box of leftover medications from previous treatments.
               Keep separate from prescriptions in the active schedule; has
               expiry dates.
- person       One of the two household users (Alex / wife). Future: kids.