<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Dashboard view model builder.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_personalprogress\view;


/**
 * Converts the aggregated API structure into compact modular cards.
 */
class dashboard_builder {
    /** @var array Default stable card order. */
    private const CARDORDER = [
        'level',
        'xp',
        'nextlevel',
        'weekly',
        'comparison',
        'streak',
        'goals',
        'quests',
        'milestones',
        'wallet',
        'course',
    ];

    /**
     * Build the Mustache view model.
     *
     * @param array $dashboard
     * @param \stdClass|null $rawconfig
     * @return array
     */
    public static function build(array $dashboard, ?\stdClass $rawconfig = null): array {
        $config = self::normalise_config($rawconfig ?? new \stdClass());
        $cards = [];

        if ($config['showlevel'] && !empty($dashboard['xp']['enabled'])) {
            $cards[] = self::card(
                'level',
                get_string('cardlevel', 'block_personalprogress'),
                get_string('levelvalue', 'block_personalprogress', (int)$dashboard['xp']['level']),
                (string)$dashboard['xp']['levelname']
            );
        }

        if ($config['showxp'] && !empty($dashboard['xp']['enabled'])) {
            $cards[] = self::card(
                'xp',
                get_string('cardxp', 'block_personalprogress'),
                get_string('xpvalue', 'block_personalprogress', self::number((int)$dashboard['xp']['total']))
            );
        }

        if ($config['shownextlevel'] && !empty($dashboard['xp']['enabled'])) {
            if (!empty($dashboard['xp']['hasnextlevel'])) {
                $cards[] = self::card(
                    'nextlevel',
                    get_string('cardnextlevel', 'block_personalprogress'),
                    get_string('xpvalue', 'block_personalprogress', self::number((int)$dashboard['xp']['nextlevel'])),
                    '',
                    true,
                    (int)$dashboard['xp']['progress'],
                    (int)$dashboard['xp']['progress'] . '%'
                );
            } else {
                $cards[] = self::card(
                    'nextlevel',
                    get_string('cardnextlevel', 'block_personalprogress'),
                    get_string('xpvalue', 'block_personalprogress', self::number((int)$dashboard['xp']['total'])),
                    (string)$dashboard['xp']['levelname'],
                    true,
                    100,
                    '100%'
                );
            }
        }

        if ($config['showweekly']) {
            if (!empty($dashboard['period']['available'])) {
                $cards[] = self::card(
                    'weekly',
                    get_string('cardweekly', 'block_personalprogress'),
                    self::signed_xp((int)$dashboard['period']['current']),
                    get_string(
                        'previousweek',
                        'block_personalprogress',
                        self::signed_xp((int)$dashboard['period']['previous'])
                    )
                );
            } else {
                $cards[] = self::empty_card(
                    'weekly',
                    get_string('cardweekly', 'block_personalprogress'),
                    get_string('historyunavailable', 'block_personalprogress')
                );
            }
        }

        if ($config['showcomparison']) {
            if (!empty($dashboard['period']['available'])) {
                $cards[] = self::card(
                    'comparison',
                    get_string('cardcomparison', 'block_personalprogress'),
                    self::signed_xp((int)$dashboard['period']['difference'])
                );
            } else {
                $cards[] = self::empty_card(
                    'comparison',
                    get_string('cardcomparison', 'block_personalprogress'),
                    get_string('historyunavailable', 'block_personalprogress')
                );
            }
        }

        if ($config['showstreak'] && !empty($dashboard['streak']['available'])) {
            if ((int)$dashboard['streak']['current'] > 0) {
                $cards[] = self::card(
                    'streak',
                    get_string('cardstreak', 'block_personalprogress'),
                    get_string('daysstreak', 'block_personalprogress', (int)$dashboard['streak']['current']),
                    get_string('beststreak', 'block_personalprogress', (int)$dashboard['streak']['best'])
                );
            } else {
                $cards[] = self::empty_card(
                    'streak',
                    get_string('cardstreak', 'block_personalprogress'),
                    get_string('nostreak', 'block_personalprogress')
                );
            }
        }

        if ($config['showgoals'] && !empty($dashboard['goals']['available'])) {
            if ((int)$dashboard['goals']['active'] > 0) {
                $cards[] = self::card(
                    'goals',
                    get_string('cardgoals', 'block_personalprogress'),
                    get_string('goalsvalue', 'block_personalprogress', (object)[
                        'completed' => (int)$dashboard['goals']['completed'],
                        'active' => (int)$dashboard['goals']['active'],
                    ])
                );
            } else {
                $cards[] = self::empty_card(
                    'goals',
                    get_string('cardgoals', 'block_personalprogress'),
                    get_string('nogoals', 'block_personalprogress')
                );
            }
        }

        if ($config['showquests'] && !empty($dashboard['quests']['available'])) {
            if ((int)$dashboard['quests']['active'] > 0) {
                $cards[] = self::card(
                    'quests',
                    get_string('cardquests', 'block_personalprogress'),
                    get_string('questsvalue', 'block_personalprogress', (int)$dashboard['quests']['active'])
                );
            } else {
                $cards[] = self::empty_card(
                    'quests',
                    get_string('cardquests', 'block_personalprogress'),
                    get_string('noquests', 'block_personalprogress')
                );
            }
        }

        if ($config['showmilestones'] && !empty($dashboard['milestones']['available'])) {
            if ((int)$dashboard['milestones']['earned'] > 0) {
                $cards[] = self::card(
                    'milestones',
                    get_string('cardmilestones', 'block_personalprogress'),
                    get_string('milestonesvalue', 'block_personalprogress', (int)$dashboard['milestones']['earned'])
                );
            } else {
                $cards[] = self::empty_card(
                    'milestones',
                    get_string('cardmilestones', 'block_personalprogress'),
                    get_string('nomilestones', 'block_personalprogress')
                );
            }
        }

        if ($config['showwallet'] && !empty($dashboard['wallet']['available'])) {
            $cards[] = self::card(
                'wallet',
                get_string('cardwallet', 'block_personalprogress'),
                get_string('creditsvalue', 'block_personalprogress', self::number((int)$dashboard['wallet']['credits']))
            );
        }

        if ($config['showcourse']) {
            if (!empty($dashboard['course']['available'])) {
                $completion = (int)$dashboard['course']['completion'];
                $cards[] = self::card(
                    'course',
                    get_string('cardcourse', 'block_personalprogress'),
                    get_string('completionvalue', 'block_personalprogress', $completion),
                    '',
                    true,
                    $completion,
                    $completion . '%'
                );
            } else {
                $cards[] = self::empty_card(
                    'course',
                    get_string('cardcourse', 'block_personalprogress'),
                    get_string('nocompletion', 'block_personalprogress')
                );
            }
        }

        usort($cards, static function(array $a, array $b) use ($config): int {
            $ao = $config['order' . $a['key']] ?? 999;
            $bo = $config['order' . $b['key']] ?? 999;
            if ($ao === $bo) {
                return array_search($a['key'], self::CARDORDER, true) <=> array_search($b['key'], self::CARDORDER, true);
            }
            return $ao <=> $bo;
        });

        $chart = self::build_chart($dashboard, $config);
        $totalxp = (int)($dashboard['xp']['total'] ?? 0);
        $hasweeklyxp = false;
        foreach (($dashboard['chart']['weeks'] ?? []) as $week) {
            if ((int)($week['xp'] ?? 0) > 0) {
                $hasweeklyxp = true;
                break;
            }
        }

        return [
            'coursename' => (string)$dashboard['meta']['coursename'],
            'cards' => $cards,
            'hascards' => !empty($cards),
            'shownodata' => $totalxp === 0 && !$hasweeklyxp,
            'nodatamessage' => get_string('nodatastart', 'block_personalprogress'),
            'chart' => $chart,
        ];
    }

    /**
     * Normalise block instance config.
     *
     * @param \stdClass $config
     * @return array
     */
    public static function normalise_config(\stdClass $config): array {
        $normalised = [];
        foreach (self::CARDORDER as $index => $card) {
            $show = 'show' . $card;
            $order = 'order' . $card;
            $configshow = 'show' . $card;
            $configorder = 'order' . $card;
            $normalised[$show] = property_exists($config, $configshow) ? !empty($config->{$configshow}) : true;
            $normalised[$order] = property_exists($config, $configorder)
                ? max(1, min(count(self::CARDORDER), (int)$config->{$configorder}))
                : $index + 1;
        }
        $normalised['showchart'] = property_exists($config, 'showchart')
            ? !empty($config->showchart)
            : true;
        $normalised['showcoursetitle'] = property_exists($config, 'showcoursetitle')
            ? !empty($config->showcoursetitle)
            : false;
        return $normalised;
    }

    /**
     * Build chart model.
     *
     * @param array $dashboard
     * @param array $config
     * @return array
     */
    private static function build_chart(array $dashboard, array $config): array {
        if (empty($config['showchart'])) {
            return [
                'show' => false,
                'label' => '',
                'empty' => true,
                'emptymessage' => '',
                'weeks' => [],
            ];
        }

        if (empty($dashboard['chart']['available'])) {
            return [
                'show' => true,
                'label' => get_string('cardchart', 'block_personalprogress'),
                'empty' => true,
                'emptymessage' => get_string('historyunavailable', 'block_personalprogress'),
                'weeks' => [],
            ];
        }

        $weeks = $dashboard['chart']['weeks'] ?? [];
        $max = 0;
        foreach ($weeks as $week) {
            $max = max($max, (int)($week['xp'] ?? 0));
        }

        $bars = [];
        foreach ($weeks as $week) {
            $xp = (int)($week['xp'] ?? 0);
            $height = $max > 0 ? (int)round(($xp / $max) * 100) : 0;
            if ($xp > 0) {
                $height = max(6, $height);
            }
            $a = (object)[
                'label' => (string)$week['label'],
                'xp' => $xp,
            ];
            $bars[] = [
                'label' => (string)$week['label'],
                'value' => $xp,
                'valueformatted' => get_string('xpvalue', 'block_personalprogress', self::number($xp)),
                'height' => $height,
                'arialabel' => get_string('weekbararia', 'block_personalprogress', $a),
            ];
        }

        return [
            'show' => true,
            'label' => get_string('cardchart', 'block_personalprogress'),
            'arialabel' => get_string('chartaria', 'block_personalprogress'),
            'empty' => $max === 0,
            'emptymessage' => $max === 0 ? get_string('nodatastart', 'block_personalprogress') : '',
            'weeks' => $bars,
        ];
    }

    /**
     * Generic card shape.
     *
     * @param string $key
     * @param string $label
     * @param string $value
     * @param string $secondary
     * @param bool $showprogress
     * @param int $progress
     * @param string $progresslabel
     * @return array
     */
    private static function card(
        string $key,
        string $label,
        string $value,
        string $secondary = '',
        bool $showprogress = false,
        int $progress = 0,
        string $progresslabel = ''
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'secondary' => $secondary,
            'hassecondary' => $secondary !== '',
            'showprogress' => $showprogress,
            'progress' => max(0, min(100, $progress)),
            'progresslabel' => $progresslabel,
            'isempty' => false,
            'emptymessage' => '',
        ];
    }

    /**
     * Empty state card.
     *
     * @param string $key
     * @param string $label
     * @param string $message
     * @return array
     */
    private static function empty_card(string $key, string $label, string $message): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => '',
            'secondary' => '',
            'hassecondary' => false,
            'showprogress' => false,
            'progress' => 0,
            'progresslabel' => '',
            'isempty' => true,
            'emptymessage' => $message,
        ];
    }

    /**
     * Localised integer.
     *
     * @param int $value
     * @return string
     */
    private static function number(int $value): string {
        return format_float($value, 0, true, true);
    }

    /**
     * XP with an explicit plus sign only for positive deltas.
     *
     * @param int $value
     * @return string
     */
    private static function signed_xp(int $value): string {
        $formatted = self::number(abs($value));
        if ($value > 0) {
            return '+' . $formatted . ' XP';
        }
        if ($value < 0) {
            return '-' . $formatted . ' XP';
        }
        return '0 XP';
    }
}
