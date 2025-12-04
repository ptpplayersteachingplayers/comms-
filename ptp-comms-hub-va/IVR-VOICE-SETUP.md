# Voice & IVR Configuration Guide

## Overview
The enhanced Voice Service allows customers to call your PTP phone number and reach real humans or leave voicemails. The Interactive Voice Response (IVR) system provides a professional phone menu.

## Key Features

### 📞 IVR Phone Menu
- Professional greeting message
- Custom menu options (1-9, 0)
- Automatic call routing to staff
- Business hours detection
- After-hours voicemail

### 🗣️ Live Call Forwarding
- Forward calls to multiple phone numbers
- Simultaneous ringing (tries all numbers at once)
- Configurable ring timeout
- Automatic voicemail fallback

### 📧 Voicemail System
- Automated voicemail recording
- Transcription with Twilio
- Teams notifications with links to recordings
- Callback integration

### ⏰ Smart Routing
- Business hours configuration
- Different menus for business hours vs after hours
- Timezone support
- Custom schedules by day

## Setup Instructions

### 1. Configure Twilio Phone Number

Your Twilio phone number needs to point to the IVR endpoint:

1. Log into [Twilio Console](https://console.twilio.com/)
2. Navigate to **Phone Numbers → Manage → Active Numbers**
3. Click on your PTP phone number
4. Under **Voice & Fax**, set:
   - **A CALL COMES IN**: Webhook
   - **URL**: `https://yoursite.com/wp-json/ptp-comms/v1/ivr-menu`
   - **HTTP Method**: POST
5. Click **Save**

### 2. Configure IVR Settings in WordPress

Go to **PTP Comms Hub → Settings → Voice & IVR** and configure:

#### Greeting Message
```
Thank you for calling PTP Soccer Camps.
```

#### Menu Prompt
```
Press 1 to speak with a camp coordinator.
Press 2 for registration information.
Press 3 for camp locations and dates.
Press 0 to repeat this menu.
```

#### Forwarding Numbers
Add staff phone numbers to receive calls (comma-separated):
```
+12155551234,+12155555678
```

**Note**: Format must include country code (e.g., +1 for US)

#### Dial Timeout
Set how long to ring before going to voicemail (default: 20 seconds)

### 3. Configure Business Hours

Enable smart routing based on your schedule:

```php
Business Hours Settings:
- Enabled: Yes
- Days: Monday, Tuesday, Wednesday, Thursday, Friday
- Start Time: 09:00 AM
- End Time: 5:00 PM
- Timezone: America/New_York
```

**During business hours**: Callers can reach live coordinators
**After hours**: Callers go directly to voicemail

### 4. Customize Messages

#### Registration Information (Option 2)
```
To register for our camps, please visit www.ptpsoccercamps.com 
or call back during business hours to speak with a coordinator.
```

#### Camp Information (Option 3)
```
For camp locations and dates, please visit www.ptpsoccercamps.com 
or check your email for our latest camp schedule.
```

#### Forward Message
```
Please hold while we connect you to a camp coordinator.
```

#### Voicemail Message
```
All of our coordinators are currently busy. Please leave a message 
after the beep, and we will return your call as soon as possible.
```

#### After Hours Message
```
Our office is currently closed. Please leave a message or visit 
our website at www.ptpsoccercamps.com.
```

## IVR Menu Options

### Default Menu Structure

**Option 1** - Connect to Human
- Rings all forwarding numbers simultaneously
- 20-second timeout
- Falls back to voicemail if no answer

**Option 2** - Registration Info
- Plays registration message
- Directs to website
- Ends call

**Option 3** - Camp Information
- Plays camp location/date message
- Directs to website
- Ends call

**Option 0** - Repeat Menu
- Replays the main menu

### Customizing Menu Options

Edit `includes/class-voice-service.php` to modify the IVR logic:

```php
public function handle_ivr_response($digit) {
    switch ($digit) {
        case '4': // Add new option
            return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="alice">Your custom message here</Say>
</Response>';
    }
}
```

## Managing Voicemails

### Viewing Voicemails

Voicemails appear in three places:
1. **Microsoft Teams** - Instant notification with recording link
2. **Dashboard** - Voicemail widget shows new/unread count
3. **Voicemail Manager** - Full list at `PTP Comms Hub → Voicemails`

### Voicemail Features

- **Listen to recording** - Click to play audio in browser
- **Read transcription** - Automatic speech-to-text
- **Caller identification** - Linked to contact if exists in database
- **Status tracking** - New, listened, callback complete
- **Quick callback** - Button to call parent back via SMS/voice

### Transcription

Twilio automatically transcribes voicemails:
- Available 1-5 minutes after voicemail is left
- Teams notification sent when ready
- Accuracy varies based on audio quality
- Stored in database for reference

## Advanced Configuration

### Multiple Forwarding Numbers

Add unlimited staff numbers to receive calls:

```
+12155551234,+12155555678,+12155559999
```

Twilio will ring all numbers simultaneously. First person to answer gets the call.

### Priority Routing

To route based on priority (tries numbers in order):

```php
// In class-voice-service.php
$twiml .= '<Number url="timeout-url-here">' . $number . '</Number>';
```

### Call Screening

Add a whisper message before connecting:

```xml
<Number>
    <Say>Call from PTP Comms Hub. Press 1 to accept.</Say>
    +12155551234
</Number>
```

### Voicemail Length

Configure max voicemail duration (default 60 seconds):

```
Recording Timeout: 120
```

## Testing Your IVR

### Test Call Flow

1. Call your Twilio number
2. Listen to greeting
3. Test each menu option:
   - Press 1: Verify staff phones ring
   - Press 2: Hear registration message
   - Press 3: Hear camp info message
   - Press 0: Menu repeats
4. Test voicemail (don't answer call)
5. Verify Teams notification arrives
6. Check voicemail in dashboard

### Common Issues

**Staff phones not ringing?**
- Verify phone numbers include +1 country code
- Check phones can receive calls
- Ensure numbers aren't in Do Not Disturb

**Voicemail not recording?**
- Check Twilio account status
- Verify webhook URL is accessible
- Check WordPress error logs

**No transcription?**
- Transcription takes 1-5 minutes
- Check Twilio account has transcription enabled
- Verify webhook callback URL

**Teams notifications missing?**
- Check Teams webhook URL is configured
- Verify notification settings are enabled
- Test Teams connection separately

## Analytics

Track IVR performance:
- **Total calls received**
- **Calls connected to humans**
- **Voicemails left**
- **Average call duration**
- **Peak call times**

View in **Dashboard → Voice Analytics**

## Best Practices

1. **Update greeting seasonally** - Mention current camp season
2. **Keep menu simple** - Max 4-5 options
3. **Test regularly** - Monthly test calls
4. **Review voicemails daily** - Return calls within 24 hours
5. **Update business hours** - Adjust for holidays/summer schedule
6. **Monitor transcriptions** - Verify accuracy
7. **Add staff numbers** - More coverage = better service

## TwiML Reference

The system uses TwiML (Twilio Markup Language) for call control:

```xml
<Response>
    <Say voice="alice">Your message</Say>
    <Gather action="url" numDigits="1">
        <Say>Press 1 for option one</Say>
    </Gather>
    <Dial timeout="20">
        <Number>+12155551234</Number>
    </Dial>
    <Record maxLength="60" transcribe="true"/>
</Response>
```

## Support

For help with IVR configuration:
- Check Twilio Console for call logs
- Review WordPress error logs
- Test with Twilio debugger
- Contact PTP support team
