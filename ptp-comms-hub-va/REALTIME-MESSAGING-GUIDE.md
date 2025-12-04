# PTP Communications Hub – Real-time messaging

This guide explains how real-time messaging works inside the PTP Comms Hub and how your staff should use it during clinics and camps.

---

## 1. What real-time messaging does

Real-time messaging adds:

- Instant sending: SMS and voice messages are sent without reloading the page.
- Auto-refresh: Conversations refresh automatically every few seconds.
- Live updates: New incoming messages appear in the thread while you are viewing it.
- Visual feedback: Clear sending, delivered, failed, and scheduled states.
- Keyboard shortcuts: Press Enter to send, Shift+Enter for a new line.
- Smart scrolling: The view stays pinned to the most recent message unless you scroll up.

The goal is simple: make it easy for your team to text parents live while events are happening.

---

## 2. Everyday PTP use cases

Use real-time messaging for:

- Clinic and camp day updates  
  Quick check-in, weather, and timing updates to parents.

- Late or missing players  
  Text families directly from the Inbox instead of using personal phones.

- On-site issues  
  Parking, field changes, indoor moves, or early pick-ups.

- Follow-up and retention  
  Send fast "thank you" or "how did it go?" messages while the experience is still fresh.

---

## 3. How it works in the Inbox

1. Go to **PTP Comms → Inbox**.
2. Select a conversation on the left panel.
3. Type your message in the composer at the bottom.
4. Choose SMS or voice if options are available.
5. Click **Send** or press Enter to send.

You will see:

- **Sending…** – Message is being submitted to Twilio.
- **Delivered** – Twilio reports that the carrier delivered the message.
- **Failed to send** – The carrier did not accept the message; you may need to correct the number or try again.
- **Scheduled** – The message is queued or pending based on your automation or timing.

Unread conversations are highlighted so staff can quickly see who still needs a reply.

---

## 4. Configuration checklist

To use real-time messaging reliably:

1. Twilio credentials are set and tested in **Settings → Twilio**.
2. Twilio webhooks are configured using the URL provided in **Settings → Webhooks**.
3. HTTPS (SSL) is active on your WordPress site.
4. jQuery and the admin JavaScript are loading on all PTP Comms Hub pages.
5. No security plugin is blocking `admin-ajax.php` requests.

If any of these are missing, real-time updates may not work consistently.

---

## 5. Basic troubleshooting

If messages are not updating in real time:

1. Refresh the page once to reset the session.
2. Open your browser console and check for JavaScript errors.
3. Confirm your Twilio Account SID, Auth Token, and phone number are correct.
4. Send a test SMS to your own phone from the Inbox.
5. Temporarily disable caching or optimization plugins for admin pages.
6. If problems continue, enable WordPress debug logging and check for PHP errors.

---

Built for PTP Soccer Camps to keep parents informed, calm, and confident while their kids train with your staff.
