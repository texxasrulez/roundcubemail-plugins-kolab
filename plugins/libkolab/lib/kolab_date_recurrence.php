<?php

/**
 * Recurrence computation class for xcal-based Kolab format objects
 *
 * Utility class to compute instances of recurring events.
 * It requires the libcalendaring PHP module to be installed and loaded.
 *
 * @version @package_version@
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2012-2016, Kolab Systems AG <contact@kolabsys.com>
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
class kolab_date_recurrence
{
    private /* EventCal */ $engine;
    private /* kolab_format_xcal */ $object;
    private /* DateTime */ $start;
    private /* DateTime */ $next;
    private /* cDateTime */ $cnext;
    private /* DateInterval */ $duration;

    /**
     * Default constructor
     *
     * @param kolab_format_xcal The Kolab object to operate on
     */
    function __construct($object)
    {
        $data = $object->to_array();

        $this->object = $object;
        $this->engine = $object->to_libcal();
        $this->start  = $this->next = $data['start'];
        $this->cnext  = kolab_format::get_datetime($this->next);

        if (is_object($data['start']) && is_object($data['end'])) {
            $this->duration = $data['start']->diff($data['end']);
        }
        else {
            // Prevent from errors when end date is not set (#5307) RFC5545 3.6.1
            $seconds = !empty($data['end']) ? ($data['end'] - $data['start']) : 0;
            $this->duration = new DateInterval('PT' . $seconds . 'S');
        }
    }

    /**
     * Get date/time of the next occurence of this event
     *
     * @param boolean Return a Unix timestamp instead of a DateTime object
     * @return mixed  DateTime object/unix timestamp or False if recurrence ended
     */
    public function next_start($timestamp = false)
    {
        $time = false;

        if ($this->engine && $this->next) {
            if (($cnext = new cDateTime($this->engine->getNextOccurence($this->cnext))) && $cnext->isValid()) {
                $next = kolab_format::php_datetime($cnext);
                $time = $timestamp ? $next->format('U') : $next;

                $this->cnext = $cnext;
                $this->next  = $next;
            }
        }

        return $time;
    }

    /**
     * Get the next recurring instance of this event
     *
     * @return mixed Array with event properties or False if recurrence ended
     */
    public function next_instance()
    {
        if ($next_start = $this->next_start()) {
            $next_end = clone $next_start;
            $next_end->add($this->duration);

            $next          = $this->object->to_array();
            $next['start'] = $next_start;
            $next['end']   = $next_end;

            $recurrence_id_format    = libkolab::recurrence_id_format($next);
            $next['recurrence_date'] = clone $next_start;
            $next['_instance']       = $next_start->format($recurrence_id_format);

            unset($next['_formatobj']);

            return $next;
        }

        return false;
    }

    /**
     * Get the end date of the occurence of this recurrence cycle
     *
     * @return DateTime|bool End datetime of the last event or False if recurrence exceeds limit
     */
    public function end()
    {
        $event = $this->object->to_array();

        // recurrence end date is given
        if ($event['recurrence']['UNTIL'] instanceof DateTime) {
            return $event['recurrence']['UNTIL'];
        }

        // let libkolab do the work
        if ($this->engine && ($cend = $this->engine->getLastOccurrence())
            && ($end_dt = kolab_format::php_datetime(new cDateTime($cend)))
        ) {
            return $end_dt;
        }

        // determine a reasonable end date if none given
        if (!$event['recurrence']['COUNT'] && $event['end'] instanceof DateTime) {
            $end_dt = clone $event['end'];
            $end_dt->add(new DateInterval('P100Y'));

            return $end_dt;
        }

        return false;
    }
}
