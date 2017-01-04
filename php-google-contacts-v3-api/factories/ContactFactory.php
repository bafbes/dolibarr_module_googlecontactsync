<?php

namespace rapidweb\googlecontacts\factories;

use rapidweb\googlecontacts\helpers\GoogleHelper;
use rapidweb\googlecontacts\objects\Contact;

abstract class ContactFactory
{
    public static function getAll()
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full?max-results=10000&updated-min=2007-03-16T00:00:00');

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContacts = simplexml_load_string($response);
        $xmlContacts->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactsArray = array();

        foreach ($xmlContacts->entry as $xmlContactsEntry) {
            $contactDetails = array();

            $contactDetails['id'] = (string) $xmlContactsEntry->id;
            $contactDetails['name'] = (string) $xmlContactsEntry->title;

            foreach ($xmlContactsEntry->children() as $key => $value) {
                $attributes = $value->attributes();

                if ($key == 'link') {
                    if ($attributes['rel'] == 'edit') {
                        $contactDetails['editURL'] = (string) $attributes['href'];
                    } elseif ($attributes['rel'] == 'self') {
                        $contactDetails['selfURL'] = (string) $attributes['href'];
                    }
                }
            }

            $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
            foreach ($contactGDNodes as $key => $value) {
                switch ($key) {
                    case 'organization':
                        $contactDetails[$key]['orgName'] = (string) $value->orgName;
                        $contactDetails[$key]['orgTitle'] = (string) $value->orgTitle;
                        break;
                    case 'email':
                        $attributes = $value->attributes();
                        $emailadress = (string) $attributes['address'];
                        $emailtype = substr(strstr($attributes['rel'], '#'), 1);
                        $contactDetails[$key][$emailtype] = $emailadress;
                        break;
                    case 'phoneNumber':
                        $attributes = $value->attributes();
                        $uri = (string) $attributes['uri'];
                        $type = substr(strstr($attributes['rel'], '#'), 1);
                        $e164 = substr(strstr($uri, ':'), 1);
                        $contactDetails[$key][$type] = $e164;
                        break;
                    default:
                        $contactDetails[$key] = (string) $value;
                        break;
                }
            }

            $contactsArray[] = new Contact($contactDetails);
        }

        return $contactsArray;
    }
    
    public static function getAllByURL($selfURL, $full = false)
    {
    	$client = GoogleHelper::getClient();
    
    	$req = new \Google_Http_Request($selfURL);
    
    	$val = $client->getAuth()->authenticatedRequest($req);
   
    	$response = $val->getResponseBody();
    	//pre(htmlentities($response),true);
    	$xml =  simplexml_load_string($response);
    	if($full) return $xml;
    	//echo pre($xml,true);
    	//echo pre(htmlentities($xml->asXML()),true);
    	
    	$Tab=array();
    	foreach ($xml->entry as $xmlEntry) {
    		
    		$Tab[] = $xmlEntry;
    		
    	}
    	
    	return $Tab;
    }
    
    
    public static function getBySelfURL($selfURL)
    {
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request($selfURL);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
/*var_dump($val);
echo $response;exit;*/
        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }

    public static function submitUpdates(Contact $updatedContact)
    {
        $client = GoogleHelper::getClient();
//var_dump($updatedContact);
//exit($updatedContact->selfURL);
        $req = new \Google_Http_Request($updatedContact->selfURL);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
       // pre(htmlentities($response),true);
        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');
        $xmlContact->registerXPathNamespace('gContact', 'http://schemas.google.com/contact/2008');
        
        $xmlContactsEntry = $xmlContact;

        $xmlContactsEntry->title = $updatedContact->name;

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();
			
			if ($key == 'email') {
                $attributes['address'] = $updatedContact->email;
            } else {
                $xmlContactsEntry->$key = $updatedContact->$key;
                $attributes['uri'] = '';
            }
        }
       
        if(!empty($updatedContact->postalAddress) && !isset($contactGDNodes->postalAddress)) {
        	$o = $xmlContactsEntry->addChild('postalAddress',$updatedContact->postalAddress,'http://schemas.google.com/g/2005');
        	$o->addAttribute('rel', 'http://schemas.google.com/g/2005#work');
        	$o->addAttribute('primary', 'true');
        	 
        }
        
        if(!empty($updatedContact->organization) && !isset($contactGDNodes->organization)) {
        	$o = $xmlContactsEntry->addChild('organization',null,'http://schemas.google.com/g/2005');
        	$o->addAttribute('rel', 'http://schemas.google.com/g/2005#work');
        	$o->addChild('orgName',$updatedContact->organization, 'http://schemas.google.com/g/2005');
        	$o->addChild('orgTitle',$updatedContact->organization_title, 'http://schemas.google.com/g/2005');
        }
        
        $contactGCNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005#kind');
        
        
        if(!empty($updatedContact->groupMembershipInfo) && !isset($contactGCNodes->groupMembershipInfo)) {
        	$o = $xmlContactsEntry->addChild('groupMembershipInfo',null,'http://schemas.google.com/contact/2008');
        	$o->addAttribute('delete', 'false');
        	$o->addAttribute('href', $updatedContact->groupMembershipInfo);
        }
          
        
        
        $updatedXML = $xmlContactsEntry->asXML();
    //   pre(htmlentities($updatedXML),true);
        $req = new \Google_Http_Request($updatedContact->editURL);
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('PUT');
        $req->setPostBody($updatedXML);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();
      //  pre(htmlentities($response),true); exit;
        $xmlContact = simplexml_load_string($response);
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');
        
        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }

    public static function create($name, $phoneNumber, $emailAddress)
    {
    	$doc = new \DOMDocument();
        $doc->formatOutput = true;
        $entry = $doc->createElement('atom:entry');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
        $doc->appendChild($entry);

        $title = $doc->createElement('title', $name);
        $entry->appendChild($title);

        $email = $doc->createElement('gd:email');
        $email->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $email->setAttribute('address', $emailAddress);
        $entry->appendChild($email);

        $contact = $doc->createElement('gd:phoneNumber', $phoneNumber);
        $contact->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $entry->appendChild($contact);

        $xmlToSend = $doc->saveXML();
        $client = GoogleHelper::getClient();

        $req = new \Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
        $req->setRequestMethod('POST');
        $req->setPostBody($xmlToSend);

        $val = $client->getAuth()->authenticatedRequest($req);

        $response = $val->getResponseBody();

        $xmlContact = simplexml_load_string($response);

        if(!empty($xmlContact->error->internalReason)) {
        	
        	return $xmlContact->error;
        	
        }
        
        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $xmlContactsEntry = $xmlContact;

        $contactDetails = array();

        $contactDetails['id'] = (string) $xmlContactsEntry->id;
        $contactDetails['name'] = (string) $xmlContactsEntry->title;

        foreach ($xmlContactsEntry->children() as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'link') {
                if ($attributes['rel'] == 'edit') {
                    $contactDetails['editURL'] = (string) $attributes['href'];
                } elseif ($attributes['rel'] == 'self') {
                    $contactDetails['selfURL'] = (string) $attributes['href'];
                }
            }
        }

        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

        foreach ($contactGDNodes as $key => $value) {
            $attributes = $value->attributes();

            if ($key == 'email') {
                $contactDetails[$key] = (string) $attributes['address'];
            } else {
                $contactDetails[$key] = (string) $value;
            }
        }

        return new Contact($contactDetails);
    }
}
