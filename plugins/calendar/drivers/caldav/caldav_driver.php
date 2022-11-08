<?php

/**
 * CalDAV driver for the Calendar plugin.
 *
 * @author Aleksander Machniak <machniak@apheleia-it.ch>
 *
 * Copyright (C) 2012-2022, Apheleia IT AG <contact@apheleia-it.ch>
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

require_once(__DIR__ . '/../kolab/kolab_driver.php');

class caldav_driver extends kolab_driver
{
    // features this backend supports
    public $alarms              = true;
    public $attendees           = true;
    public $freebusy            = true;
    public $attachments         = false; // TODO
    public $undelete            = false; // TODO
    public $alarm_types         = ['DISPLAY', 'AUDIO'];
    public $categoriesimmutable = true;

    /**
     * Default constructor
     */
    public function __construct($cal)
    {
        $cal->require_plugin('libkolab');

        // load helper classes *after* libkolab has been loaded (#3248)
        require_once(__DIR__ . '/caldav_calendar.php');
        // require_once(__DIR__ . '/kolab_user_calendar.php');
        // require_once(__DIR__ . '/caldav_invitation_calendar.php');

        $this->cal = $cal;
        $this->rc  = $cal->rc;

        // Initialize the CalDAV storage
        $url = $this->rc->config->get('calendar_caldav_server', 'http://localhost');
        $this->storage = new kolab_storage_dav($url);

        $this->cal->register_action('push-freebusy', [$this, 'push_freebusy']);
        $this->cal->register_action('calendar-acl', [$this, 'calendar_acl']);

        // $this->freebusy_trigger = $this->rc->config->get('calendar_freebusy_trigger', false);

        // TODO: get configuration for the Bonnie API
        // $this->bonnie_api = libkolab::get_bonnie_api();
    }

    /**
     * Read available calendars from server
     */
    protected function _read_calendars()
    {
        // already read sources
        if (isset($this->calendars)) {
            return $this->calendars;
        }

        // get all folders that support VEVENT, sorted by namespace/name
        $folders = $this->storage->get_folders('event');
            // + $this->storage->get_user_folders('event', true);

        $this->calendars = [];

        foreach ($folders as $folder) {
            $calendar = $this->_to_calendar($folder);
            if ($calendar->ready) {
                $this->calendars[$calendar->id] = $calendar;
                if ($calendar->editable) {
                    $this->has_writeable = true;
                }
            }
        }

        return $this->calendars;
    }

    /**
     * Convert kolab_storage_folder into caldav_calendar
     */
    protected function _to_calendar($folder)
    {
        if ($folder instanceof caldav_calendar) {
            return $folder;
        }

        if ($folder instanceof kolab_storage_folder_user) {
            $calendar = new kolab_user_calendar($folder, $this->cal);
            $calendar->subscriptions = count($folder->children) > 0;
        }
        else {
            $calendar = new caldav_calendar($folder, $this->cal);
        }

        return $calendar;
    }

    /**
     * Get a list of available calendars from this source.
     *
     * @param int    $filter Bitmask defining filter criterias
     * @param object $tree   Reference to hierarchical folder tree object
     *
     * @return array List of calendars
     */
    public function list_calendars($filter = 0, &$tree = null)
    {
        $this->_read_calendars();

        $folders   = $this->filter_calendars($filter);
        $calendars = [];
        $prefs     = $this->rc->config->get('kolab_calendars', []);

        // include virtual folders for a full folder tree
/*
        if (!is_null($tree)) {
            $folders = $this->storage->folder_hierarchy($folders, $tree);
        }
*/
        $parents = array_keys($this->calendars);

        foreach ($folders as $id => $cal) {
/*
            $path = explode('/', $cal->name);

            // find parent
            do {
                array_pop($path);
                $parent_id = $this->storage->folder_id(implode('/', $path));
            }
            while (count($path) > 1 && !in_array($parent_id, $parents));

            // restore "real" parent ID
            if ($parent_id && !in_array($parent_id, $parents)) {
                $parent_id = $this->storage->folder_id($cal->get_parent());
            }

            $parents[] = $cal->id;

            if ($cal->virtual) {
                $calendars[$cal->id] = [
                    'id'       => $cal->id,
                    'name'     => $cal->get_name(),
                    'listname' => $cal->get_foldername(),
                    'editname' => $cal->get_foldername(),
                    'virtual'  => true,
                    'editable' => false,
                    'group'    => $cal->get_namespace(),
                ];
            }
            else {
*/
                // additional folders may come from kolab_storage_dav::folder_hierarchy() above
                // make sure we deal with caldav_calendar instances
                $cal = $this->_to_calendar($cal);
                $this->calendars[$cal->id] = $cal;

                $is_user = ($cal instanceof caldav_user_calendar);

                $calendars[$cal->id] = [
                    'id'        => $cal->id,
                    'name'      => $cal->get_name(),
                    'listname'  => $cal->get_foldername(),
                    'editname'  => $cal->get_foldername(),
                    'title'     => '', // $cal->get_title(),
                    'color'     => $cal->get_color(),
                    'editable'  => $cal->editable,
                    'group'     => $is_user ? 'other user' : $cal->get_namespace(),
                    'active'    => !isset($prefs[$cal->id]['active']) || !empty($prefs[$cal->id]['active']),
                    'owner'     => $cal->get_owner(),
                    'removable' => !$cal->default,
                    // extras to hide some elements in the UI
                    'subscriptions' => $cal->subscriptions,
                    'driver' => 'caldav',
                ];

                if (!$is_user) {
                    $calendars[$cal->id] += [
                        'default'    => $cal->default,
                        'rights'     => $cal->rights,
                        'showalarms' => $cal->alarms,
                        'history'    => !empty($this->bonnie_api),
                        'children'   => true,  // TODO: determine if that folder indeed has child folders
                        'parent'     => $parent_id,
                        'subtype'    => $cal->subtype,
                        'caldavurl'  => '', // $cal->get_caldav_url(),
                    ];
                }
/*
            }
*/
            if ($cal->subscriptions) {
                $calendars[$cal->id]['subscribed'] = $cal->is_subscribed();
            }
        }
/*
        // list virtual calendars showing invitations
        if ($this->rc->config->get('kolab_invitation_calendars') && !($filter & self::FILTER_INSERTABLE)) {
            foreach ([self::INVITATIONS_CALENDAR_PENDING, self::INVITATIONS_CALENDAR_DECLINED] as $id) {
                $cal = new caldav_invitation_calendar($id, $this->cal);
                if (!($filter & self::FILTER_ACTIVE) || $cal->is_active()) {
                    $calendars[$id] = [
                        'id'         => $cal->id,
                        'name'       => $cal->get_name(),
                        'listname'   => $cal->get_name(),
                        'editname'   => $cal->get_foldername(),
                        'title'      => $cal->get_title(),
                        'color'      => $cal->get_color(),
                        'editable'   => $cal->editable,
                        'rights'     => $cal->rights,
                        'showalarms' => $cal->alarms,
                        'history'    => !empty($this->bonnie_api),
                        'group'      => 'x-invitations',
                        'default'    => false,
                        'active'     => $cal->is_active(),
                        'owner'      => $cal->get_owner(),
                        'children'   => false,
                        'counts'     => $id == self::INVITATIONS_CALENDAR_PENDING,
                    ];


                    if (is_object($tree)) {
                        $tree->children[] = $cal;
                    }
                }
            }
        }
*/
        // append the virtual birthdays calendar
        if ($this->rc->config->get('calendar_contact_birthdays', false) && !($filter & self::FILTER_INSERTABLE)) {
            $id    = self::BIRTHDAY_CALENDAR_ID;
            $prefs = $this->rc->config->get('kolab_calendars', []);  // read local prefs

            if (!($filter & self::FILTER_ACTIVE) || !empty($prefs[$id]['active'])) {
                $calendars[$id] = [
                    'id'         => $id,
                    'name'       => $this->cal->gettext('birthdays'),
                    'listname'   => $this->cal->gettext('birthdays'),
                    'color'      => !empty($prefs[$id]['color']) ? $prefs[$id]['color'] : '87CEFA',
                    'active'     => !empty($prefs[$id]['active']),
                    'showalarms' => (bool) $this->rc->config->get('calendar_birthdays_alarm_type'),
                    'group'      => 'x-birthdays',
                    'editable'   => false,
                    'default'    => false,
                    'children'   => false,
                    'history'    => false,
                ];
            }
        }

        return $calendars;
    }

    /**
     * Get the caldav_calendar instance for the given calendar ID
     *
     * @param string Calendar identifier
     *
     * @return ?caldav_calendar Object nor null if calendar doesn't exist
     */
    public function get_calendar($id)
    {
        $this->_read_calendars();

        // create calendar object if necessary
        if (empty($this->calendars[$id])) {
            if (in_array($id, [self::INVITATIONS_CALENDAR_PENDING, self::INVITATIONS_CALENDAR_DECLINED])) {
                return new caldav_invitation_calendar($id, $this->cal);
            }

            // for unsubscribed calendar folders
            if ($id !== self::BIRTHDAY_CALENDAR_ID) {
                $calendar = caldav_calendar::factory($id, $this->cal);
                if ($calendar->ready) {
                    $this->calendars[$calendar->id] = $calendar;
                }
            }
        }

        return !empty($this->calendars[$id]) ? $this->calendars[$id] : null;
    }

    /**
     * Create a new calendar assigned to the current user
     *
     * @param array Hash array with calendar properties
     *    name: Calendar name
     *   color: The color of the calendar
     *
     * @return mixed ID of the calendar on success, False on error
     */
    public function create_calendar($prop)
    {
        $prop['type']   = 'event';
        $prop['alarms'] = !empty($prop['showalarms']);

        $id = $this->storage->folder_update($prop);

        if ($id === false) {
            return false;
        }

        $prefs['kolab_calendars'] = $this->rc->config->get('kolab_calendars', []);
        $prefs['kolab_calendars'][$id]['active'] = true;

        $this->rc->user->save_prefs($prefs);

        return $id;
    }

    /**
     * Update properties of an existing calendar
     *
     * @see calendar_driver::edit_calendar()
     */
    public function edit_calendar($prop)
    {
        $id = $prop['id'];

        if (!in_array($id, [self::BIRTHDAY_CALENDAR_ID, self::INVITATIONS_CALENDAR_PENDING, self::INVITATIONS_CALENDAR_DECLINED])) {
            $prop['type']   = 'event';
            $prop['alarms'] = !empty($prop['showalarms']);

            return $this->storage->folder_update($prop) !== false;
        }

        // fallback to local prefs for special calendars
        $prefs['kolab_calendars'] = $this->rc->config->get('kolab_calendars', []);
        unset($prefs['kolab_calendars'][$id]['showalarms']);

        if (isset($prop['showalarms']) && $id == self::BIRTHDAY_CALENDAR_ID) {
            $prefs['calendar_birthdays_alarm_type'] = $prop['showalarms'] ? $this->alarm_types[0] : '';
        }
        else if (isset($prop['showalarms'])) {
            $prefs['kolab_calendars'][$id]['showalarms'] = !empty($prop['showalarms']);
        }

        if (!empty($prefs['kolab_calendars'][$id])) {
            $this->rc->user->save_prefs($prefs);
        }

        return true;
    }

    /**
     * Set active/subscribed state of a calendar
     *
     * @see calendar_driver::subscribe_calendar()
     */
    public function subscribe_calendar($prop)
    {
        if (empty($prop['id'])) {
            return false;
        }

        // save state in local prefs
        if (isset($prop['active'])) {
            $prefs['kolab_calendars'] = $this->rc->config->get('kolab_calendars', []);
            $prefs['kolab_calendars'][$prop['id']]['active'] = !empty($prop['active']);

            $this->rc->user->save_prefs($prefs);
        }

        return true;
    }

    /**
     * Delete the given calendar with all its contents
     *
     * @see calendar_driver::delete_calendar()
     */
    public function delete_calendar($prop)
    {
        if (!empty($prop['id'])) {
            if ($this->storage->folder_delete($prop['id'], 'event')) {
                // remove folder from user prefs
                $prefs['kolab_calendars'] = $this->rc->config->get('kolab_calendars', []);
                if (isset($prefs['kolab_calendars'][$prop['id']])) {
                    unset($prefs['kolab_calendars'][$prop['id']]);
                    $this->rc->user->save_prefs($prefs);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Search for shared or otherwise not listed calendars the user has access
     *
     * @param string Search string
     * @param string Section/source to search
     *
     * @return array List of calendars
     */
    public function search_calendars($query, $source)
    {
        $this->calendars = [];
        $this->search_more_results = false;
/*
        // find unsubscribed IMAP folders that have "event" type
        if ($source == 'folders') {
            foreach ((array) $this->storage->search_folders('event', $query, ['other']) as $folder) {
                $calendar = new kolab_calendar($folder->name, $this->cal);
                $this->calendars[$calendar->id] = $calendar;
            }
        }
        // find other user's virtual calendars
        else if ($source == 'users') {
            // we have slightly more space, so display twice the number
            $limit = $this->rc->config->get('autocomplete_max', 15) * 2;

            foreach ($this->storage->search_users($query, 0, [], $limit, $count) as $user) {
                $calendar = new caldav_user_calendar($user, $this->cal);
                $this->calendars[$calendar->id] = $calendar;

                // search for calendar folders shared by this user
                foreach ($this->storage->list_user_folders($user, 'event', false) as $foldername) {
                    $cal = new caldav_calendar($foldername, $this->cal);
                    $this->calendars[$cal->id] = $cal;
                    $calendar->subscriptions = true;
                }
            }

            if ($count > $limit) {
                $this->search_more_results = true;
            }
        }

        // don't list the birthday calendar
        $this->rc->config->set('calendar_contact_birthdays', false);
        $this->rc->config->set('kolab_invitation_calendars', false);
*/
        return $this->list_calendars();
    }

    /**
     * Get events from source.
     *
     * @param int    Event's new start (unix timestamp)
     * @param int    Event's new end (unix timestamp)
     * @param string Search query (optional)
     * @param mixed  List of calendar IDs to load events from (either as array or comma-separated string)
     * @param bool   Include virtual events (optional)
     * @param int    Only list events modified since this time (unix timestamp)
     *
     * @return array A list of event records
     */
    public function load_events($start, $end, $search = null, $calendars = null, $virtual = 1, $modifiedsince = null)
    {
        if ($calendars && is_string($calendars)) {
            $calendars = explode(',', $calendars);
        }
        else if (!$calendars) {
            $this->_read_calendars();
            $calendars = array_keys($this->calendars);
        }

        $query      = [];
        $events     = [];
        $categories = [];

        if ($modifiedsince) {
            $query[] = ['changed', '>=', $modifiedsince];
        }

        foreach ($calendars as $cid) {
            if ($storage = $this->get_calendar($cid)) {
                $events = array_merge($events, $storage->list_events($start, $end, $search, $virtual, $query));
                $categories += $storage->categories;
            }
        }

        // add events from the address books birthday calendar
        if (in_array(self::BIRTHDAY_CALENDAR_ID, $calendars)) {
            $events = array_merge($events, $this->load_birthday_events($start, $end, $search, $modifiedsince));
        }

        // add new categories to user prefs
        $old_categories = $this->rc->config->get('calendar_categories', $this->default_categories);
        $newcats = array_udiff(
            array_keys($categories),
            array_keys($old_categories),
            function($a, $b) { return strcasecmp($a, $b); }
        );

        if (!empty($newcats)) {
            foreach ($newcats as $category) {
                $old_categories[$category] = '';  // no color set yet
            }
            $this->rc->user->save_prefs(['calendar_categories' => $old_categories]);
        }

        array_walk($events, 'caldav_driver::to_rcube_event');

        return $events;
    }

    /**
     * Create instances of a recurring event
     *
     * @param array    Hash array with event properties
     * @param DateTime Start date of the recurrence window
     * @param DateTime End date of the recurrence window
     *
     * @return array List of recurring event instances
     */
    public function get_recurring_events($event, $start, $end = null)
    {
        // load the given event data into a libkolabxml container
        $event_xml = new kolab_format_event();
        $event_xml->set($event);
        $event['_formatobj'] = $event_xml;

        $this->_read_calendars();
        $storage = reset($this->calendars);

        return $storage->get_recurring_events($event, $start, $end);
    }

    /**
     *
     */
    protected function get_recurrence_count($event, $dtstart)
    {
        // load the given event data into a libkolabxml container
        $event_xml = new kolab_format_event();
        $event_xml->set($event);
        $event['_formatobj'] = $event_xml;

        // use libkolab to compute recurring events
        $recurrence = new kolab_date_recurrence($event['_formatobj']);

        $count = 0;
        while (($next_event = $recurrence->next_instance()) && $next_event['start'] <= $dtstart && $count < 1000) {
            $count++;
        }

        return $count;
    }

    /**
     * Callback function to produce driver-specific calendar create/edit form
     *
     * @param string Request action 'form-edit|form-new'
     * @param array  Calendar properties (e.g. id, color)
     * @param array  Edit form fields
     *
     * @return string HTML content of the form
     */
    public function calendar_form($action, $calendar, $formfields)
    {
        $special_calendars = [
            self::BIRTHDAY_CALENDAR_ID,
            self::INVITATIONS_CALENDAR_PENDING,
            self::INVITATIONS_CALENDAR_DECLINED
        ];

        // show default dialog for birthday calendar
        if (in_array($calendar['id'], $special_calendars)) {
            if ($calendar['id'] != self::BIRTHDAY_CALENDAR_ID) {
                unset($formfields['showalarms']);
            }

            // General tab
            $form['props'] = [
                'name'   => $this->rc->gettext('properties'),
                'fields' => $formfields,
            ];

            return kolab_utils::folder_form($form, '', 'calendar');
        }

        $form['props'] = [
            'name'   => $this->rc->gettext('properties'),
            'fields' => [
                'location' => $formfields['name'],
                'color'    => $formfields['color'],
                'alarms'   => $formfields['showalarms'],
            ],
        ];

        return kolab_utils::folder_form($form, $folder, 'calendar', [], true);
    }
}
