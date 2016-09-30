<?php
/**
 * WebHook Plugin for LimeSurvey
 * Use question text to create a report and send it by email.
 *
 * @author Matthew Cohen <mccandu@gmail.com>
 * @license GPL v3
 * @version 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class WebHook extends PluginBase {

    protected $storage = 'DbStorage';    
    static protected $description = 'Add call a url on completion of a survey';
    static protected $name = 'WebHook';
    
    public function __construct(PluginManager $manager, $id) 
    {

        parent::__construct($manager, $id);
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');

    }

    public function beforeSurveySettings()
    {

        $event = $this->event;
        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'webhookurlinfo' => array(
                    'type' => 'info',
                    'content' => '<p>Set the webhook URL to be called on completion of the survey.</p><p>You must set a url scheme (http or https).</p><p>You can use any of the following tags in the URL as parameter values:<ul><li><strong>{TID}</strong> Participant ID</li><li><strong>{TOKEN}</strong> Participant Token</li><li><strong>{SURVEYID}</strong> Survey ID</li></ul></p>'
                ),
                'webhookurl'=>array(
                    'type'=>'string',
                    'label'=>'Webhook URL',
                    'help'=>'',
                    'current' => $this->get('webhookurl', 'Survey', $event->get('survey'),$this->get('webhookurl',null,null,$this->settings['webhookurl']['default'])),
                )
            )
         ));

    }

    public function newSurveySettings()
    {

        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            $default=$event->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $event->get('survey'),$default);
        }

    }

    public function afterSurveyComplete() 
    {
      
        $event      = $this->event;
        $surveyId   = $event->get('surveyId');
        $responseId = $event->get('responseId');
        
        if(isset($responseId)){

            $url = $this->getURL($surveyId,$responseId);

            if($url!=""){
                $result = $this->httpGet($url);
                if($result=="200"){
                    //$this->log("Webhook [Ok]");
                }
                else{
                    //$this->log("Webhook [Error] Invalid URL", CLogger::LEVEL_ERROR);
                }
            }
            else{
                //$this->log("Webhook [Error] Bad Status", CLogger::LEVEL_ERROR);
            }

        }

    }

    private function getURL($surveyId,$responseId){

        $url = $this->get('webhookurl', 'Survey', $surveyId);

        if (!filter_var($url, FILTER_VALIDATE_URL, array(FILTER_FLAG_HOST_REQUIRED,FILTER_FLAG_SCHEME_REQUIRED)) === false) {
            $parts = parse_url($url);
            if($parts['query']){
                $url = $this->getURLQuery($url,$surveyId,$responseId);
            }
            return $url;
        }

    }   

    private function getURLQuery($url,$surveyId,$responseId=null){

        $patterns = array();
        $replacements = array();
        $patterns[0] = '/{SURVEYID}/';
        $replacements[0] = $surveyId;

        if(isset($responseId)){
            $participant = $this->getParticipant($surveyId, $responseId);
            if(isset($participant )){
                $patterns[1] = '/{TOKEN}/';          
                $patterns[2] = '/{TID}/';
                $replacements[1] = $participant['token'];
                $replacements[2] = $participant['tid'];
            }
        }

        return preg_replace($patterns, $replacements, $url);   

    }

    private function getParticipant($surveyId, $responseId){

        $response  = $this->pluginManager->getAPI()->getResponse($surveyId, $responseId);
        if(isset($response['token'])){
            $participant = $this->pluginManager->getAPI()->getToken($surveyId, $response['token']);
            $return['token'] = $response['token'];
            $return['tid'] = $participant->tid;
            return $return;            
        }

    }

    private function httpGet($url){

        $ch = curl_init();  
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_HEADER, true);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpcode;

    }

}