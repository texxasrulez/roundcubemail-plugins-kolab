<?php

/**
 * libcalendaring_recurrence tests
 *
 * @author Aleksander Machniak <machniak@apheleia-it.ch>
 *
 * Copyright (C) 2022, Apheleia IT AG <contact@apheleia-it.ch>
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

class RecurrenceTest extends PHPUnit\Framework\TestCase
{
    private $plugin;

    function setUp(): void
    {
        $rcube = rcmail::get_instance();
        $rcube->plugins->load_plugin('libcalendaring', true, true);

        $this->plugin = $rcube->plugins->get_plugin('libcalendaring');
    }

    /**
     * Test for libcalendaring_recurrence::first_occurrence()
     *
     * @dataProvider data_first_occurrence
     */
    function test_first_occurrence($recurrence_data, $start, $expected)
    {
        $start = new DateTime($start);
        if (!empty($recurrence_data['UNTIL'])) {
            $recurrence_data['UNTIL'] = new DateTime($recurrence_data['UNTIL']);
        }

        $recurrence = $this->plugin->get_recurrence();

        $recurrence->init($recurrence_data, $start);
        $first = $recurrence->first_occurrence();

        $this->assertEquals($expected, $first ? $first->format('Y-m-d H:i:s') : '');
    }

    /**
     * Data for test_first_occurrence()
     */
    function data_first_occurrence()
    {
        // TODO: BYYEARDAY, BYWEEKNO, BYSETPOS, WKST

        return array(
            // non-recurring
            array(
                array(),                                     // recurrence data
                '2017-08-31 11:00:00',                       // start date
                '2017-08-31 11:00:00',                       // expected result
            ),
            // daily
            array(
                array('FREQ' => 'DAILY', 'INTERVAL' => '1'), // recurrence data
                '2017-08-31 11:00:00',                       // start date
                '2017-08-31 11:00:00',                       // expected result
            ),
            // TODO: this one is not supported by the Calendar UI
/*
            array(
                array('FREQ' => 'DAILY', 'INTERVAL' => '1', 'BYMONTH' => 1),
                '2017-08-31 11:00:00',
                '2018-01-01 11:00:00',
            ),
*/
            // weekly
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '1'),
                '2017-08-31 11:00:00', // Thursday
                '2017-08-31 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '1', 'BYDAY' => 'WE'),
                '2017-08-31 11:00:00', // Thursday
                '2017-09-06 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '1', 'BYDAY' => 'TH'),
                '2017-08-31 11:00:00', // Thursday
                '2017-08-31 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '1', 'BYDAY' => 'FR'),
                '2017-08-31 11:00:00', // Thursday
                '2017-09-01 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '2'),
                '2017-08-31 11:00:00', // Thursday
                '2017-08-31 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '3', 'BYDAY' => 'WE'),
                '2017-08-31 11:00:00', // Thursday
                '2017-09-20 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '1', 'BYDAY' => 'WE', 'COUNT' => 1),
                '2017-08-31 11:00:00', // Thursday
                '2017-09-06 11:00:00',
            ),
            array(
                array('FREQ' => 'WEEKLY', 'INTERVAL' => '1', 'BYDAY' => 'WE', 'UNTIL' => '2017-09-01'),
                '2017-08-31 11:00:00', // Thursday
                '',
            ),
            // monthly
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '1'),
                '2017-09-08 11:00:00',
                '2017-09-08 11:00:00',
            ),
/*
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '1', 'BYMONTHDAY' => '8,9'),
                '2017-08-31 11:00:00',
                '2017-09-08 11:00:00',
            ),
*/
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '1', 'BYMONTHDAY' => '8,9'),
                '2017-09-08 11:00:00',
                '2017-09-08 11:00:00',
            ),
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '1', 'BYDAY' => '1WE'),
                '2017-08-16 11:00:00',
                '2017-09-06 11:00:00',
            ),
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '1', 'BYDAY' => '-1WE'),
                '2017-08-16 11:00:00',
                '2017-08-30 11:00:00',
            ),
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '2'),
                '2017-09-08 11:00:00',
                '2017-09-08 11:00:00',
            ),
/*
            array(
                array('FREQ' => 'MONTHLY', 'INTERVAL' => '2', 'BYMONTHDAY' => '8'),
                '2017-08-31 11:00:00',
                '2017-09-08 11:00:00', // ??????
            ),
*/
            // yearly
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '1'),
                '2017-08-16 12:00:00',
                '2017-08-16 12:00:00',
            ),
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '1', 'BYMONTH' => '8'),
                '2017-08-16 12:00:00',
                '2017-08-16 12:00:00',
            ),
/*
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '1', 'BYDAY' => '-1MO'),
                '2017-08-16 11:00:00',
                '2017-12-25 11:00:00',
            ),
*/
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '1', 'BYMONTH' => '8', 'BYDAY' => '-1MO'),
                '2017-08-16 11:00:00',
                '2017-08-28 11:00:00',
            ),
/*
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '1', 'BYMONTH' => '1', 'BYDAY' => '1MO'),
                '2017-08-16 11:00:00',
                '2018-01-01 11:00:00',
            ),
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '1', 'BYMONTH' => '1,9', 'BYDAY' => '1MO'),
                '2017-08-16 11:00:00',
                '2017-09-04 11:00:00',
            ),
*/
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '2'),
                '2017-08-16 11:00:00',
                '2017-08-16 11:00:00',
            ),
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '2', 'BYMONTH' => '8'),
                '2017-08-16 11:00:00',
                '2017-08-16 11:00:00',
            ),
/*
            array(
                array('FREQ' => 'YEARLY', 'INTERVAL' => '2', 'BYDAY' => '-1MO'),
                '2017-08-16 11:00:00',
                '2017-12-25 11:00:00',
            ),
*/
            // on dates (FIXME: do we really expect the first occurrence to be on the start date?)
            array(
                array('RDATE' => array(new DateTime('2017-08-10 11:00:00 Europe/Warsaw'))),
                '2017-08-01 11:00:00',
                '2017-08-01 11:00:00',
            ),
        );
    }

    /**
     * Test for libcalendaring_recurrence::first_occurrence() for all-day events
     *
     * @dataProvider data_first_occurrence
     */
    function test_first_occurrence_allday($recurrence_data, $start, $expected)
    {
        $start = new libcalendaring_datetime($start);
        $start->_dateonly = true;

        if (!empty($recurrence_data['UNTIL'])) {
            $recurrence_data['UNTIL'] = new DateTime($recurrence_data['UNTIL']);
        }

        $recurrence = $this->plugin->get_recurrence();

        $recurrence->init($recurrence_data, $start);
        $first = $recurrence->first_occurrence();

        $this->assertEquals($expected, $first ? $first->format('Y-m-d H:i:s') : '');

        if ($expected) {
            $this->assertTrue($first->_dateonly);
        }
    }

    /**
     * Test for libcalendaring_recurrence::next_instance()
     */
    function test_next_instance()
    {
        date_default_timezone_set('America/New_York');

        $start = new libcalendaring_datetime('2017-08-31 11:00:00', new DateTimeZone('Europe/Berlin'));
        $event = [
            'start'      => $start,
            'recurrence' => ['FREQ' => 'WEEKLY', 'INTERVAL' => '1'],
            'allday'     => true,
        ];

        $recurrence = new libcalendaring_recurrence($this->plugin, $event);
        $next       = $recurrence->next_instance();

        $this->assertEquals($start->format('2017-09-07 H:i:s'), $next['start']->format('Y-m-d H:i:s'), 'Same time');
        $this->assertEquals($start->getTimezone()->getName(), $next['start']->getTimezone()->getName(), 'Same timezone');
        $this->assertTrue($next['start']->_dateonly, '_dateonly flag');
    }
}
