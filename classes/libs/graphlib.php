<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_excursions
 * @copyright 2023 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_excursions\libs;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/excursions/vendor/autoload.php');
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http;
use Microsoft\Graph\Model;
use GuzzleHttp\Client;

class graphlib {

    private static Client $tokenClient;
    private static string $appToken;
    private static Graph $appClient;


    public static function getAppOnlyToken(): string {
        // If we already have a token, just return it
        // Tokens are valid for one hour, after that a new token needs to be
        // requested
        if (isset(graphlib::$appToken)) {
            return graphlib::$appToken;
        }

        $tokenClient = new Client();
        $config = get_config('local_excursions');            
        $clientId = $config->graphclientid;
        $clientSecret = $config->graphclientsecret;
        $tenantId = $config->graphtenantid;

        //echo "<pre>"; 
        //var_export([$clientId, $clientSecret, $tenantId]); 
        //exit;

        // https://learn.microsoft.com/azure/active-directory/develop/v2-oauth2-client-creds-grant-flow
        $tokenRequestUrl = 'https://login.microsoftonline.com/'.$tenantId.'/oauth2/v2.0/token';

        // POST to the /token endpoint
        $tokenResponse = $tokenClient->post($tokenRequestUrl, [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default'
            ],
            // These options are needed to enable getting
            // the response body from a 4xx response
            'http_errors' => false,
            'curl' => [
                CURLOPT_FAILONERROR => false
            ]
        ]);

        $responseBody = json_decode($tokenResponse->getBody()->getContents());
        if ($tokenResponse->getStatusCode() == 200) {
            // Return the access token
            graphlib::$appToken = $responseBody->access_token;
            return $responseBody->access_token;
        } else {
            $error = isset($responseBody->error) ? $responseBody->error : $tokenResponse->getStatusCode();
            throw new Exception('Token endpoint returned '.$error, 100);
        }
    }


    // returns one of these: https://github.com/microsoftgraph/msgraph-sdk-php/blob/94aba6eca383e2963440ac463d5ea8f1603a192c/src/Http/GraphCollectionRequest.php
    public static function listCalendarEvents($userPrincipalName, $fromdate, $todate): Http\GraphCollectionRequest {
        $appClient = new Graph();
        $token = graphlib::getAppOnlyToken();
        $appClient->setAccessToken($token);
        $filter = '$filter=start/dateTime gt \'' . $fromdate . '\' and start/dateTime lt \'' . $todate . '\'';
        $orderby = '$orderby=start/dateTime';
        $requestUrl = '/users/' . $userPrincipalName . '/events?' . $filter.'&'.$orderby;

        return $appClient->createCollectionRequest('GET', $requestUrl)
                         ->setReturnType(Model\Event::class)
                         ->setPageSize(999);
    }


    // 
    /*
        ----------------------
        CREATE EVENT
        ----------------------
        Request and return details: https://learn.microsoft.com/en-us/graph/api/calendar-post-events?view=graph-rest-1.0&tabs=http
        ------
        JSON
        ------
        {
            "subject": "Let's go for lunch",
            "body": {
            "contentType": "HTML",
            "content": "Does next month work for you?"
            },
            "start": {
                "dateTime": "2019-03-10T12:00:00",
                "timeZone": "Pacific Standard Time"
            },
            "end": {
                "dateTime": "2019-03-10T14:00:00",
                "timeZone": "Pacific Standard Time"
            },
            "location":{
                "displayName":"Harry's Bar"
            },
            "isOnlineMeeting": false,
        }
        ------
        PHP
        ------
        $eventdata = new stdClass();
        $eventdata->subject = "Let's go for lunch";
        $eventdata->body = new stdClass();
        $eventdata->body->contentType = "HTML";
        $eventdata->body->content = "<b>Does</b> next month work for you?";
        $eventdata->start = new stdClass();
        $eventdata->start->dateTime = "2023-07-21T15:11:00";
        $eventdata->start->timeZone = "AUS Eastern Standard Time";
        $eventdata->end = new stdClass();
        $eventdata->end->dateTime = "2023-07-21T16:12:00";
        $eventdata->end->timeZone = "AUS Eastern Standard Time";
        $eventdata->location = new stdClass();
        $eventdata->location->displayName = "Data centre";
        $eventdata->isOnlineMeeting = false;
    */
    public static function createEvent($userPrincipalName, $eventData) {
        $token = graphlib::getAppOnlyToken();
        $appClient = (new Graph())->setAccessToken($token);
        
        $requestUrl = "/users/$userPrincipalName/events";

        // Based on: https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/tests/Functional/MailTest.php#L49
        $result = $appClient->createRequest("POST", $requestUrl)
                        ->attachBody($eventData)
                        ->setReturnType(Model\Event::class)
                        ->execute();

        return $result;
    }

    /*
        ----------------------
        GET EVENT
        ----------------------
        Request and return details: https://learn.microsoft.com/en-us/graph/api/event-get?view=graph-rest-1.0&tabs=http
        ------
    */
    public static function getEvent($userPrincipalName, $id) {
        $token = graphlib::getAppOnlyToken();
        $appClient = (new Graph())->setAccessToken($token);
        
        $requestUrl = "/users/$userPrincipalName/events/$id";

        // Based on https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/tests/Functional/MailTest.php#L21
        $result = $appClient->createRequest("GET", $requestUrl)
                            ->setReturnType(Model\Event::class)
                            ->execute();
        return $result;
    }

    /*
        ----------------------
        UPDATE EVENT
        ----------------------
        Request and return details: https://learn.microsoft.com/en-us/graph/api/event-update?view=graph-rest-1.0&tabs=http
        ------
    */
    public static function updateEvent($userPrincipalName, $id, $eventData) {
        $token = graphlib::getAppOnlyToken();
        $appClient = (new Graph())->setAccessToken($token);
        
        $requestUrl = "/users/$userPrincipalName/events/$id";

        // Based on https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/tests/Functional/MailTest.php#L21
        $result = $appClient->createRequest("PATCH", $requestUrl)
                            ->attachBody($eventData)
                            ->setReturnType(Model\Event::class)
                            ->execute();
        return $result;
    }


    /*
        ----------------------
        DELETE EVENT
        ----------------------
        Request and return details: https://learn.microsoft.com/en-us/graph/api/event-delete?view=graph-rest-1.0&tabs=http
        ------
    */
    public static function deleteEvent($userPrincipalName, $id) {
        $token = graphlib::getAppOnlyToken();
        $appClient = (new Graph())->setAccessToken($token);
        
        $requestUrl = "/users/$userPrincipalName/events/$id";

        $result = $appClient->createRequest("DELETE", $requestUrl)
                            ->execute();
        return $result;
    }




    
}