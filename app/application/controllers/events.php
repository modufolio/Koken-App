<?php

class Events extends Koken_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function _all($params)
    {
        $s = new Setting();
        $s->where('name', 'site_timezone')->get();
        $tz = new DateTimeZone($s->value ?? 'UTC');
        $offset = $tz->getOffset(new DateTime('now', new DateTimeZone('UTC')));

        if ($offset === 0) {
            $shift = '';
        } else {
            $shift = ($offset < 0 ? '-' : '+') . abs($offset);
        }

        $content_col = $params['content_column'];
        $select = "COUNT(*) as count, YEAR(FROM_UNIXTIME($content_col{$shift})) as event_year";
        $group = 'event_year';
        $order = 'event_year DESC';

        if (!$params['scope'] || $params['scope'] !== 'year') {
            $select .= ", MONTH(FROM_UNIXTIME($content_col{$shift})) as event_month";
            $group .= ',event_month';
            $order .= ',event_month DESC';
        }

        if (!$params['limit_to'] && !$params['scope']) {
            $select .= ", DAY(FROM_UNIXTIME($content_col{$shift})) as event_day";
            $group .= ',event_day';
            $order .= ',event_day DESC';
        }

        $a = new Album();
        $c = new Content();
        $t = new Text();

        $c->select(str_replace($content_col, $c->table . '.' . $content_col, $select))
            ->include_related('album', 'id')
            ->where('visibility', 0)
            ->where('deleted', 0);

        if (!$params['limit_to']) {
            $c->group_start()
                ->where('album_id', null)
                ->or_where($c->table . '.published_on > `' . $a->table . '`.`published_on`')
            ->group_end();
        }

        $a->select(str_replace($content_col, 'published_on', $select))
                    ->where('visibility', 0)
                    ->where('deleted', 0)
                    ->where('total_count >', 0);

        $t->select(str_replace($content_col, 'published_on', $select))
                    ->where('page_type', 0)
                    ->where('published', 1);

        if ($params['featured']) {
            $c->where('featured', 1);
            $a->where('featured', 1);
            $t->where('featured', 1);
        }

        $c->group_by($group)->order_by($order);
        $t->group_by($group)->order_by($order);
        $a->group_by($group)->order_by($order);

        $dates = [];

        if (!$params['limit_to'] || $params['limit_to'] === 'content') {
            foreach ($c->get() as $content) {
                if ($params['scope'] === 'year') {
                    $key = "{$content->event_year}";
                } elseif ($params['limit_to'] || $params['scope']) {
                    $key = "{$content->event_year}-{$content->event_month}";
                } else {
                    $key = "{$content->event_year}-{$content->event_month}-{$content->event_day}";
                }
                $key = strtotime($key);

                if (is_numeric($content->event_year)) {
                    $dates[$key] = ['year' => (int) $content->event_year, 'month' => (int) $content->event_month, 'day' => (int) $content->event_day, 'counts' => ['content' => (int) $content->count, 'albums' => 0, 'essays' => 0]];
                }
            }
        }

        if (!$params['limit_to'] || $params['limit_to'] === 'albums') {
            foreach ($a->get() as $album) {
                if ($params['scope'] === 'year') {
                    $key = "{$album->event_year}";
                } elseif ($params['limit_to'] || $params['scope']) {
                    $key = "{$album->event_year}-{$album->event_month}";
                } else {
                    $key = "{$album->event_year}-{$album->event_month}-{$album->event_day}";
                }
                $key = strtotime($key);

                if (is_numeric($album->event_year)) {
                    if (!isset($dates[$key])) {
                        $dates[$key] = ['year' => (int) $album->event_year, 'month' => (int) $album->event_month, 'day' => (int) $album->event_day, 'counts' => ['content' => 0, 'albums' => (int) $album->count, 'essays' => 0]];
                    } else {
                        $dates[$key]['counts']['albums'] = (int) $album->count;
                    }
                }
            }
        }

        if (!$params['limit_to'] || $params['limit_to'] === 'essays') {
            foreach ($t->get() as $essay) {
                if ($params['scope'] === 'year') {
                    $key = "{$essay->event_year}";
                } elseif ($params['limit_to'] || $params['scope']) {
                    $key = "{$essay->event_year}-{$essay->event_month}";
                } else {
                    $key = "{$essay->event_year}-{$essay->event_month}-{$essay->event_day}";
                }

                $key = strtotime($key);

                if (is_numeric($essay->event_year)) {
                    if (!isset($dates[$key])) {
                        $dates[$key] = ['year' => (int) $essay->event_year, 'month' => (int) $essay->event_month, 'day' => (int) $essay->event_day, 'counts' => ['content' => 0, 'albums' => 0, 'essays' => (int) $essay->count]];
                    } else {
                        $dates[$key]['counts']['essays'] = (int) $essay->count;
                    }
                }
            }
        }

        krsort($dates);

        return $dates;
    }

    public function index()
    {
        [$params, ] = $this->parse_params(func_get_args());

        $defaults = ['year' => false, 'month' => false, 'limit_to' => false, 'limit' => false, 'content_column' => 'published_on', 'featured' => false, 'scope' => false, 'load_items' => false];

        $params = array_merge($defaults, $params);

        if ($params['limit'] && !$params['scope']) {
            $params['limit'] = min($params['limit'], 10);
        } elseif (!$params['scope']) {
            $params['limit'] = 10;
        }

        if ($params['limit_to']) {
            $params['limit'] = false;
        }

        $all = $this->_all($params);

        $t = new Tag();
        $urls = $t->form_urls();
        $url_base = $t->get_base();

        if ($params['year']) {
            if (count($all) === 1) {
                $context = ['total' => 1, 'position' => 1, 'previous' => [], 'next' => []];
            } elseif ($params['month']) {
                $next = $previous = $current = false;
                $marker = $i = $pos = 0;
                $dates = [];

                foreach ($all as $event) {
                    $_marker = "{$event['year']}-{$event['month']}";
                    if ($_marker !== $marker) {
                        $i++;
                        $_marker = $marker;
                    }
                    if ($event['year'] == $params['year'] && $event['month'] == $params['month']) {
                        $pos = $i;
                        $current = true;
                        $dates[] = $event;
                    } elseif ($current && !$next) {
                        $next = ['year' => $event['year'], 'month' => $event['month']];
                    } elseif (!$current) {
                        $previous = ['year' => $event['year'], 'month' => $event['month']];
                    }
                }
            } else {
                $next = $previous = $current = false;
                $year = $i = $pos = 0;
                $dates = [];
                foreach ($all as $event) {
                    if ($event['year'] !== $year) {
                        $year = $event['year'];
                        $i++;
                    }
                    if ($event['year'] == $params['year']) {
                        $pos = $i;
                        $current = true;
                        $dates[] = $event;
                    } elseif ($current && !$next) {
                        $next = ['year' => $event['year']];
                    } elseif (!$current) {
                        $previous = ['year' => $event['year']];
                    }
                }
            }

            if ($next) {
                $this->archive_urls($next, $urls, $url_base, $params);
                $next = [$next];
            } else {
                $next = [];
            }

            if ($previous) {
                $this->archive_urls($previous, $urls, $url_base, $params);
                $previous = [$previous];
            } else {
                $previous = [];
            }

            $context = ['total' => $i, 'position' => $pos, 'previous' => $previous, 'next' => $next];
        } else {
            $dates = $all;
        }

        $total = count($dates);

        if ($params['limit']) {
            $stream = ['page' => isset($params['page']) ? (int) $params['page'] : 1, 'pages' => ceil($total/$params['limit']), 'per_page' => min($params['limit'], $total), 'total' => $total, 'events' => []];
            $dates = array_slice($dates, ($stream['page']-1)*$params['limit'], $params['limit']);
        } else {
            $stream = ['total' => $total, 'events' => []];
        }

        if ($params['year']) {
            $event = ['year' => (int) $params['year']];

            $this->archive_urls($event, $urls, $url_base, $params);

            $event['context'] = $context;

            $stream['event'] = $event;
        }

        foreach ($dates as $event) {
            if ($params['limit_to']) {
                $c = $event['counts'][$params['limit_to']];
                unset($event['counts']);
                unset($event['day']);

                $event['counts'][$params['limit_to']] = $event['counts']['total'] = $c;
            }
            if ($params['scope']) {
                unset($event['day']);
                if ($params['scope'] === 'year') {
                    unset($event['month']);
                }
            }

            if ($params['load_items']) {
                [$items, $cs] = $this->aggregate('date', ['year' => $event['year'], 'month' => $event['month'], 'day' => $event['day'], 'limit' => 50]);
                $event['items'] = $items['items'];
            } else {
                $this->event_urls($event, $urls, $url_base, $params);
            }
            $stream['events'][] = $event;
        }

        $this->set_response_data($stream);
    }

    private function archive_urls(&$event, $urls, $url_base, $params)
    {
        if ($params['month'] && !isset($event['month'])) {
            $event['month'] = (int) $params['month'];
        }

        if ($urls['archive_timeline']) {
            $event['__koken_url'] = $urls['timeline'] .  $event['year'] . '/';
            if ($params['month']) {
                $event['__koken_url'] .= str_pad((string) $event['month'], 2, '0', STR_PAD_LEFT) . '/';
            }
            $event['url'] = $url_base . $event['__koken_url'];
        } else {
            $event['__koken_url'] = $event['url'] = false;
        }
    }

    private function event_urls(&$event, $urls, $url_base, $params = false)
    {
        $koken_url_info = $this->config->item('koken_url_info');
        $base = $koken_url_info->base;

        if (isset($event['month'])) {
            $m = str_pad((string) $event['month'], 2, '0', STR_PAD_LEFT);
        }

        if ($params && $params['limit_to'] || $params['scope']) {
            if ($params['limit_to']) {
                $key = 'archive_' . ($params['limit_to'] === 'content' ? 'contents' : $params['limit_to']);
                if (isset($urls[$key]) && $urls[$key]) {
                    $event['__koken_url'] = str_replace(':year', $event['year'], $urls[$key]);
                    $event['__koken_url'] = str_replace(['/:day', '(?:', ')?'], '', str_replace(':month', $m, $event['__koken_url']));
                } else {
                    $event['__koken_url'] = false;
                }
            } else {
                if ($urls['archive_timeline']) {
                    $event['__koken_url'] = $urls['timeline'] .  $event['year'] . ($params['scope'] !== 'year' ? '/' .  $m : '') . '/';
                } else {
                    $event['__koken_url'] = false;
                }
            }
        } else {
            $d = str_pad((string) $event['day'], 2, '0', STR_PAD_LEFT);
            $tail = '/year:' . $event['year'] . '/month:' . $event['month'] . '/day:' . $event['day'] . '/reduce:1';
            $event['__koken_url'] = isset($urls['event_timeline']) && $urls['event_timeline'] ? $urls['timeline'] .  $event['year'] . '/' .  $m . '/' . $d . '/' : false;
            $event['items'] = $base . "api.php?/events/{$event['year']}-$m-$d";
            $event['content'] = $event['counts']['content'] > 0 ? $base . 'api.php?/content/order_by:published_on' . $tail : [];
            $event['albums'] = $event['counts']['albums'] > 0 ? $base . 'api.php?/albums/order_by:published_on/include_empty:0' . $tail : [];
            $event['essays'] = $event['counts']['essays'] > 0 ? $base . 'api.php?/text/page_type:essay' . $tail : [];
        }

        $event['__koken__'] = 'event';

        if ($event['__koken_url']) {
            $event['url'] = $url_base . $event['__koken_url'] . (defined('DRAFT_CONTEXT') && !is_numeric(DRAFT_CONTEXT) ? '&preview=' . DRAFT_CONTEXT : '');
        } else {
            $event['url'] = false;
        }
    }

    public function show()
    {
       [$params, $id] = $this->parse_params(func_get_args());

        if (isset($params['limit'])) {
            $params['limit'] = min($params['limit'], 50);
        } else {
            $params['limit'] = 50;
        }

        $all = $this->_all(['year' => false, 'month' => false, 'limit_to' => false, 'limit' => false, 'content_column' => 'published_on', 'featured' => false, 'scope' => false]);

        preg_match('/(\d{4})\-(\d{1,2})\-(\d{1,2})/', (string) $id, $matches);

        [, $year, $month, $day] = $matches;

        $month = (int) $month;
        $day = (int) $day;

        [$stream, $counts] = $this->aggregate('date', array_merge($params, ['year' => $year, 'month' => $month, 'day' => $day]));

        $t = new Tag();
        $urls = $t->form_urls();
        $url_base = $t->get_base();

        if (count($all) === 1) {
            $context = ['total' => 1, 'position' => 1, 'previous' => [], 'next' => []];
        } else {
            $next = $previous = $current = false;
            $i = 1;

            foreach ($all as $e) {
                if ($e['year'] == $year && $e['month'] == $month && $e['day'] == $day) {
                    $current = true;
                } elseif ($current) {
                    $next = $e;
                    break;
                } else {
                    $i++;
                    $previous = $e;
                }
            }

            if ($next) {
                $this->event_urls($next, $urls, $url_base);
                $next = [$next];
            } else {
                $next = [];
            }

            if ($previous) {
                $this->event_urls($previous, $urls, $url_base);
                $previous = [$previous];
            } else {
                $previous = [];
            }

            $context = ['total' => count($all), 'position' => $i, 'previous' => $previous, 'next' => $next];
        }

        $stream['event'] = ['year' => (int) $year, 'month' => (int) $month, 'day' => (int) $day, 'counts' => $counts, 'context' => $context];

        $this->event_urls($stream['event'], $urls, $url_base);

        $this->set_response_data($stream);
    }
}
