# PTP Comms Hub Enterprise - Start Here

Welcome to PTP Communications Hub Enterprise! This guide will help you get started quickly.

## Quick Start (5 minutes)

### Step 1: Activate Plugin
✓ Already done if you're reading this!

### Step 2: Configure Twilio
1. Go to **PTP Comms → Settings → Twilio**
2. Add your Twilio Account SID, Auth Token, and Phone Number
3. Click **Save Settings**

### Step 3: Configure Twilio Webhook (CRITICAL for Enterprise Features)
**Required for parent-initiated messages and two-way SMS**

1. Copy webhook URL from Settings → Twilio section
2. Go to [Twilio Console](https://console.twilio.com)
3. Configure your phone number's messaging webhook
4. **See `TWILIO-WEBHOOK-SETUP.md` for detailed instructions**

Without this step, parents can only **reply** to campaigns. With it, they can **initiate** conversations anytime!

### Step 4: Import Contacts
1. Go to **PTP Comms → Contacts**
2. Click **Import CSV**
3. Upload your parent contact list
4. Review import results

### Step 5: Send Test Campaign
1. Go to **PTP Comms → Campaigns**
2. Click **Create Campaign**
3. Write your message
4. Send to yourself first to test
5. Then send to all contacts

## Essential Guides

### For Setup
- **`TWILIO-WEBHOOK-SETUP.md`** - Two-way SMS configuration (MUST READ)
- **`SETUP-GUIDE.md`** - Detailed configuration walkthrough
- **`DEPLOYMENT-CHECKLIST.md`** - Pre-launch verification

### For Daily Use
- **`ENTERPRISE-FEATURES-GUIDE.md`** - Complete feature reference
- **`FEATURES-COMPLETE.md`** - All capabilities overview

### For Developers
- **`ADMIN-UPDATES-v2.md`** - UI customization guide

## Enterprise Features Enabled

Once webhook is configured, you get:

- **Two-Way SMS Messaging** - Parents can text you anytime  
- **Automatic Contact Creation** - Unknown numbers auto-added  
- **Conversation Threading** - Full message history per contact  
- **Auto Opt-In/Opt-Out** - STOP/START keyword handling  
- **Microsoft Teams Integration** - Real-time message notifications  
- **HubSpot Sync** - CRM integration with deals & contacts  
- **WooCommerce Automation** - Auto-message on orders  
- **Smart Automations** - Event-triggered messaging  
- **Campaign Management** - Bulk SMS to filtered audiences  
- **Message Templates** - Pre-written responses with merge tags  

## Configuration Checklist

Before going live, ensure:

- [ ] Twilio credentials configured
- [ ] Twilio webhook URL added to Twilio Console
- [ ] Test SMS sent and received
- [ ] Contacts imported with opt-in status
- [ ] HubSpot connected (optional but recommended)
- [ ] Microsoft Teams webhook configured (optional but recommended)
- [ ] WooCommerce order automation tested
- [ ] Message templates created
- [ ] Staff trained on Inbox usage
- [ ] Parents informed they can text you

## 💡 Recommended First Campaign

**Subject:** "You Can Text Us Now!"

**Message:**
```
Hi {parent_first_name}! PTP Soccer Camps here. 

Did you know you can now TEXT us directly at this number for:
• Registration questions
• Weather updates  
• Schedule changes

We respond during business hours M-F 9am-5pm.

Save this number!

Reply STOP to opt-out.
```

This educates parents about the two-way capability!

## 🆘 Need Help?

### Quick Troubleshooting

**Messages not appearing in Inbox?**
→ See `TWILIO-WEBHOOK-SETUP.md` section "Troubleshooting"

**Can't send messages?**
→ Check Settings → Twilio credentials and contact opt-in status

**HubSpot not syncing?**
→ Verify API key and required scopes in Settings → HubSpot

**Webhook 404 errors?**
→ Go to Settings → Permalinks and click "Save Changes"

### Full Documentation

- **Complete Feature Guide:** `ENTERPRISE-FEATURES-GUIDE.md`
- **Deployment Checklist:** `DEPLOYMENT-CHECKLIST.md`
- **Detailed Setup:** `SETUP-GUIDE.md`

## 🎓 Training Your Team

### For Inbox Agents (Reply to Messages)
- Show them **PTP Comms → Inbox**
- Explain unread badges
- Demo replying to conversations
- Review message templates

### For Campaign Managers (Send Broadcasts)
- Show them **PTP Comms → Campaigns**
- Walk through campaign creation
- Explain audience filtering
- Review scheduling options

### For Administrators (System Config)
- Show them **PTP Comms → Settings**
- Review all integration settings
- Explain automation rules
- Discuss reporting & logs

## 📈 Success Tips

### Week 1
- Import all contacts with proper opt-in status
- Configure webhook for two-way messaging
- Send welcome campaign announcing text capability
- Train staff on Inbox usage

### Month 1
- Set up 3-5 key automations (order confirmations, reminders)
- Create library of message templates
- Configure HubSpot + Microsoft Teams integrations
- Monitor engagement metrics

### Ongoing
- Review weekly campaign performance
- Maintain clean contact list
- Update templates seasonally
- Train new staff as needed
- Monitor message costs vs. value

## You're Ready!

Everything is configured and ready to go. Your next steps:

1. Read `TWILIO-WEBHOOK-SETUP.md` and configure webhook
2. Import your contact list
3. Send your first test campaign
4. Train your team
5. Announce to parents

**Welcome to enterprise-grade SMS communication for PTP Soccer Camps!**

---

Questions? Check the other `.md` guides in this folder for detailed information on every feature.
