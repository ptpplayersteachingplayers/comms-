# Twilio Webhook Setup Guide

## Enable Two-Way SMS Messaging

For parents to message your PTP phone number independently (not just reply to campaigns), you must configure your Twilio webhook.

### Step 1: Get Your Webhook URL

Your webhook URL is:
```
https://yoursite.com/ptp-comms/sms-webhook
```

**Replace `yoursite.com` with your actual WordPress site domain.**

### Step 2: Configure Twilio

1. **Log in to Twilio Console:** https://console.twilio.com

2. **Navigate to Phone Numbers:**
   - Click "Phone Numbers" in the left sidebar
   - Click "Manage" → "Active numbers"
   - Click on your PTP phone number

3. **Configure Messaging:**
   - Scroll to the "Messaging" section
   - Under "A MESSAGE COMES IN":
     - Set to "Webhook"
     - Enter your webhook URL: `https://yoursite.com/ptp-comms/sms-webhook`
     - Set HTTP method to "POST"
   - Click "Save"

### Step 3: Test Two-Way Messaging

1. **Send a test SMS** from your personal phone to your PTP number
2. **Check the Inbox** in PTP Communications Hub
3. **Reply from the admin** to verify two-way communication

### What Happens When Parents Message You

1. **New Message Arrives**
   - Twilio receives the SMS
   - Webhook creates/updates contact
   - Message appears in Inbox
   - Microsoft Teams notification sent (if configured)
   - Unread counter increases

2. **Auto-Responses**
   - "STOP" → Auto opt-out confirmation
   - "START" → Auto opt-in welcome message
   - All other messages → Added to inbox

3. **You Reply**
   - Type response in Inbox conversation view
   - Click "Send SMS" or "Call"
   - Parent receives message via Twilio
   - Conversation thread updated

### Webhook Security (Optional but Recommended)

For production sites, add Twilio webhook validation:

1. **Get Auth Token** from Twilio Console → Account Info
2. **Add to Settings:** PTP Comms → Settings → Twilio → Auth Token
3. The webhook will validate signatures automatically

### Troubleshooting

**Messages not appearing in Inbox?**
- ✓ Check webhook URL is exactly correct (no trailing slash issues)
- ✓ Verify WordPress permalinks are enabled (Settings → Permalinks → Post name)
- ✓ Check PHP error logs
- ✓ Test webhook URL directly: `curl https://yoursite.com/ptp-comms/sms-webhook`

**Getting 404 errors?**
- Go to Settings → Permalinks in WordPress
- Click "Save Changes" to flush rewrite rules
- Try the webhook URL again

**Contact not created?**
- Check database tables exist
- Verify plugin activation ran successfully
- Check PHP/MySQL error logs

### Enterprise Features Enabled

Once webhook is configured, these features work automatically:

**Parent-Initiated Conversations** - Parents can text anytime  
**Two-Way Messaging** - Reply directly from admin  
**Automatic Contact Creation** - Unknown numbers auto-added  
**Opt-In/Opt-Out Management** - STOP/START handled automatically  
**Conversation Threading** - All messages grouped by contact  
**Microsoft Teams Integration** - Real-time notifications  
**Message History** - Full conversation logs  
**Unread Tracking** - Never miss an important message  

### Support & Testing

**Test Webhook Manually:**

```bash
curl -X POST https://yoursite.com/ptp-comms/sms-webhook \
  -d "From=%2B15555551234" \
  -d "Body=Test message" \
  -d "MessageSid=SM1234567890"
```

**Expected Response:** Blank page (webhook processes in background)

**Check Inbox:** Message should appear from contact

### Best Practices

1. **Monitor Regularly** - Check Inbox daily for new messages
2. **Respond Promptly** - Parents expect timely replies
3. **Use Templates** - Create quick reply templates for common questions
4. **Enable Microsoft Teams** - Get instant notifications on new messages
5. **Train Staff** - Ensure team knows how to use Inbox
6. **Set Expectations** - Tell parents they can text you directly

### Next Steps

After webhook is configured:

1. ✓ Test with your own phone
2. ✓ Train staff on Inbox usage
3. ✓ Create message templates
4. ✓ Enable Microsoft Teams notifications
5. ✓ Announce to parents they can text you

---

**Need Help?**

Common issues:
- Webhook URL formatting
- Permalink configuration
- Twilio account permissions
- Server firewall rules

Check server logs and WordPress debug log for detailed error messages.
