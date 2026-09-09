# CONTEXT — Medication Manager domain glossary

Canonical terms agreed as the domain model evolved. Update this file only when
a term's meaning is challenged or a new one crystallises. No implementation
detail lives here.

- medication   A drug given to a person. The thing the schedule and stock
               both talk about.
- package      A supply unit of a medication (one blister, one box). Has
               stock that can run out (e.g. N doses left). Refill forecasting
               is about packages.
- person       One of the two household users (Alex / wife). Future: kids.
- dosing pattern  How a medication's doses are timed. Exactly one per
               medication: either `interval` ("every N hours") or
               `fixed-times` (specific clock times each day).
- interval     The interval-based dosing pattern: medicines are taken every N
               hours. The cadence is a continuous, rolling clock re-anchored
               to the LAST actual dose — there is no daily reset and
               no midnight clamp. In this household's context (Venezuela),
               "every N hours" (e.g. every 8h) is the norm for acute meds.
- fixed-times  The clock-time dosing pattern: doses at specific times of day
               (e.g. 8:00 and 20:00). The day resets nightly.
- intake       A single scheduled dose of a medication. For fixed-times meds
               it is a specific clock-time slot in the day's grid; for
               interval meds intakes are NOT stored as a daily grid — the
               schedule is expressed as a due time (below).
- schedule     How and when a medication's doses are taken, per its dosing
               pattern. For fixed-times meds it is the ordered set of clock
               times; for interval meds it is the interval plus an optional
               nominal start alignment.
- dose event   The record of what happened to a dose: taken on time, taken
               late (with the actual time), or skipped.
- due time     For an interval med, the single rolling fact: the next dose is
               due at last actual dose + interval. Drives reminders and the
               "today" view.
- shift        For fixed-times meds, the re-timing of that day's remaining
               intakes after a late dose (within the day; resets next day).
               For interval meds there is no "shift" — the cadence simply
               re-anchors to the last actual dose.
- skip         A dose intentionally not taken. For interval meds it deletes
               that cadence slot (the next due time still derives from the
               last actual dose — nothing compresses, nothing is caught up).
               For fixed-times meds it flags the intake and leaves the rest
               of the day fixed.
- home inventory  The box of leftover medications from previous treatments.
               Keep separate from prescriptions in the active schedule; has
               expiry dates.