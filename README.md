# Personal Progress (`block_personalprogress`)

Personal Progress is a compact learner dashboard that combines the student's own progress into modular cards. It is a presentation and aggregation layer: it never awards XP, changes balances, completes goals or writes progress back into the source plugins.

The block follows one rule throughout the interface: the learner is compared only with their own previous progress. There are no leaderboards, positions, percentiles, class averages or messages that compare one learner with another.

## What the learner can see

Depending on the cards enabled in the block instance, the dashboard can show:

- current level and Personal XP total;
- XP required by the next level and progress toward it;
- XP earned in the current week and previous week;
- the learner's own week-to-week difference;
- an 8-week personal XP evolution chart;
- current streak and personal best from `block_personalstreak`;
- active and completed personal goals from `local_personalgoals`;
- quests currently in progress from `local_xpquests`;
- milestones earned from `local_xpmilestones`;
- available reward credits from `local_rewardshop`;
- Moodle course completion percentage.

Every card can be enabled or disabled and assigned a position in the block configuration. Cards backed by an optional plugin disappear cleanly when that plugin is not available.

## Dashboard aggregation

The public PHP API is:

```php
$dashboard = \block_personalprogress\api::get_dashboard($userid, $courseid);
```

It returns source-oriented data rather than HTML, for example:

```php
[
    'xp' => [
        'total' => 4850,
        'level' => 7,
        'nextlevel' => 5000,
        'progress' => 97,
    ],
    'period' => [
        'current' => 320,
        'previous' => 250,
        'difference' => 70,
    ],
    'streak' => [
        'available' => true,
        'current' => 6,
        'best' => 14,
    ],
    'goals' => [
        'available' => true,
        'active' => 3,
        'completed' => 2,
    ],
    'quests' => [
        'available' => true,
        'active' => 1,
    ],
    'milestones' => [
        'available' => true,
        'earned' => 12,
    ],
    'wallet' => [
        'available' => true,
        'credits' => 320,
    ],
    'course' => [
        'available' => true,
        'completion' => 63,
    ],
]
```

The browser does not receive a userid parameter. The AJAX method always loads the authenticated user's own dashboard and returns all visible cards in one request, which avoids independent requests for XP, streak, goals, quests and wallet data.

## Personal XP history

The block reads totals and levels through `local_personalxp\service\xp_manager::get_course_display_state()` and never reads Personal XP tables directly.

Weekly comparison and the 8-week chart use the public method:

```php
\local_personalxp\service\xp_manager::get_awards_between(
    $userid,
    $courseid,
    $fromtimestamp,
    $untiltimestamp
);
```

The returned award timestamps are grouped into calendar weeks in the learner's Moodle timezone. This keeps the data ownership in Personal XP while allowing presentation plugins to build period views without coupling themselves to its database schema.

## Optional integration API contracts

Optional plugins are checked with `core_component::get_plugin_directory()` before any optional class is autoloaded. The preferred public contracts are:

```php
\block_personalstreak\api::get_summary($userid, $courseid);
\local_personalgoals\api::get_summary($userid, $courseid);
\local_xpquests\api::get_summary($userid, $courseid);
\local_xpmilestones\api::get_summary($userid, $courseid);
\local_rewardshop\api::get_wallet($userid, $courseid);
```

For compatibility, the streak adapter also accepts `get_streak()` and the reward shop adapter also accepts `get_summary()`. No fallback reads another plugin's database tables.

## Dashboard and course pages

Inside a course, the block uses that course as its scope. On Moodle Dashboard or site pages, where there is no real course context, it selects the learner's most recently accessed active enrolled course. This deliberately avoids inventing a cross-course XP total that `local_personalxp` itself does not define.

## Empty states

A learner with no XP sees a neutral invitation to start activities and follow their own evolution. A learner without a streak is encouraged to study on different days, while a learner without goals is simply told that no personal goals have been defined yet. Missing progress is never presented as failure and there is no red/green judgment based on comparison with other learners.

## Rendering and accessibility

The initial block contains a compact skeleton because dashboard data is loaded asynchronously. One aggregated AJAX call replaces it with a Mustache template. The 8-week chart is plain HTML/CSS, so there is no global chart library and no conflict with themes or other plugins.

Progress indicators expose `role="progressbar"`, labels and values to assistive technology. Chart bars contain accessible labels, the layout adapts from side columns to wider dashboard areas, and loading animation is disabled when `prefers-reduced-motion` is active.

## Cache behaviour

The aggregated dashboard uses Moodle MUC with a short 60-second TTL. Source plugins remain authoritative. Integrations that need immediate refresh after a user-visible change can call:

```php
\block_personalprogress\api::purge_cache($userid, $courseid);
```
