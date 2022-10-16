<?php

/**
 * Kolab storage cache class for calendar event objects
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2013, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class kolab_storage_dav_cache_event extends kolab_storage_dav_cache
{
    protected $extra_cols = ['dtstart','dtend'];
    protected $data_props = ['categories', 'status', 'attendees'];
    protected $fulltext_cols = ['title', 'description', 'location', 'attendees:name', 'attendees:email', 'categories'];

    /**
     * Helper method to convert the given Kolab object into a dataset to be written to cache
     *
     * @override
     */
    protected function _serialize($object)
    {
        $sql_data = parent::_serialize($object);

        $sql_data['dtstart'] = $this->_convert_datetime($object['start']);
        $sql_data['dtend']   = $this->_convert_datetime($object['end']);

        // extend date range for recurring events
        if (!empty($object['recurrence'])) {
            if (empty($object['_formatobj'])) {
                $event_xml = new kolab_format_event();
                $event_xml->set($object);
                $object['_formatobj'] = $event_xml;
            }

            $recurrence = new kolab_date_recurrence($object['_formatobj']);
            $dtend = $recurrence->end() ?: new DateTime('now +100 years');
            $sql_data['dtend'] = $this->_convert_datetime($dtend);
        }

        // extend start/end dates to spawn all exceptions
        if (is_array($object['exceptions'])) {
            foreach ($object['exceptions'] as $exception) {
                if (is_a($exception['start'], 'DateTime')) {
                    $exstart = $this->_convert_datetime($exception['start']);
                    if ($exstart < $sql_data['dtstart']) {
                        $sql_data['dtstart'] = $exstart;
                    }
                }
                if (is_a($exception['end'], 'DateTime')) {
                    $exend = $this->_convert_datetime($exception['end']);
                    if ($exend > $sql_data['dtend']) {
                        $sql_data['dtend'] = $exend;
                    }
                }
            }
        }

        $sql_data['tags']  = ' ' . join(' ', $this->get_tags($object)) . ' ';  // pad with spaces for strict/prefix search
        $sql_data['words'] = ' ' . join(' ', $this->get_words($object)) . ' ';

        return $sql_data;
    }

    /**
     * Callback to get words to index for fulltext search
     *
     * @return array List of words to save in cache
     */
    public function get_words($object = [])
    {
        $data = '';

        foreach ($this->fulltext_cols as $colname) {
            list($col, $field) = explode(':', $colname);

            if ($field) {
                $a = [];
                foreach ((array) $object[$col] as $attr) {
                    $a[] = $attr[$field];
                }
                $val = join(' ', $a);
            }
            else {
                $val = is_array($object[$col]) ? join(' ', $object[$col]) : $object[$col];
            }

            if (strlen($val))
                $data .= $val . ' ';
        }

        $words = rcube_utils::normalize_string($data, true);

        // collect words from recurrence exceptions
        if (is_array($object['exceptions'])) {
            foreach ($object['exceptions'] as $exception) {
                $words = array_merge($words, $this->get_words($exception));
            }
        }

        return array_unique($words);
    }

    /**
     * Callback to get object specific tags to cache
     *
     * @return array List of tags to save in cache
     */
    public function get_tags($object)
    {
        $tags = [];

        if (!empty($object['valarms'])) {
            $tags[] = 'x-has-alarms';
        }

        // create tags reflecting participant status
        if (is_array($object['attendees'])) {
            foreach ($object['attendees'] as $attendee) {
                if (!empty($attendee['email']) && !empty($attendee['status']))
                    $tags[] = 'x-partstat:' . $attendee['email'] . ':' . strtolower($attendee['status']);
            }
        }

        // collect tags from recurrence exceptions
        if (is_array($object['exceptions'])) {
            foreach ($object['exceptions'] as $exception) {
                $tags = array_merge($tags, $this->get_tags($exception));
            }
        }

        if (!empty($object['status'])) {
          $tags[] = 'x-status:' . strtolower($object['status']);
        }

        return array_unique($tags);
    }
}
