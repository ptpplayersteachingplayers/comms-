# PTP Comms Hub - Settings Page Styling Reference

## Color Palette

### Primary Colors
- **PTP Yellow:** `#FCB900` - Primary brand color, used for accents, borders, and highlights
- **PTP Ink:** `#0e0f11` - Main text color, headings
- **PTP Background:** `#f4f3f0` - Page background

### UI Colors
- **Success Green:** `#22c55e` - Success messages, positive indicators
- **Warning Orange:** `#f59e0b` - Warning messages, attention needed
- **Muted Gray:** `#6b7280` - Helper text, secondary information
- **Border Gray:** `#e5e7eb` - Borders, dividers

## Typography

### Headings
- **H1:** 28px, weight 700, color: ptp-ink
- **H2:** 20px, weight 600, color: ptp-ink
- **Labels:** 14px, weight 600, color: ptp-ink
- **Helper Text:** 13px, italic, color: ptp-muted

## Form Elements

### Input Fields
```css
padding: 10px 14px;
border: 2px solid var(--ptp-border);
border-radius: 8px;
max-width: 500px;

/* On Focus */
border-color: var(--ptp-yellow);
box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
```

### Checkboxes
- Display inline with labels
- 10px gap between checkbox and text
- Helper text indented 32px (aligned with label text)

### Buttons
```css
/* Primary Button */
background: linear-gradient(135deg, #FCB900, #e5a700);
color: #0e0f11;
padding: 12px 24px;
border-radius: 8px;
font-weight: 600;
```

## Components

### Alert Boxes
```css
/* Success Alert */
background: rgba(34, 197, 94, 0.1);
border-left: 4px solid #22c55e;
color: #166534;

/* Warning Alert */
background: rgba(245, 158, 11, 0.1);
border-left: 4px solid #f59e0b;
color: #92400e;
```

### Info Boxes
```css
background: linear-gradient(135deg, #fff9e6, #fffbf0);
border: 1px solid #FCB900;
border-left: 4px solid #FCB900;
padding: 20px;
border-radius: 8px;
```

### Tabs
```css
/* Inactive Tab */
color: #6b7280;
padding: 10px 18px;

/* Active Tab */
background: rgba(252, 185, 0, 0.1);
border-bottom: 3px solid #FCB900;
color: #0e0f11;
font-weight: 600;
```

### Cards
```css
background: #ffffff;
padding: 30px;
border-radius: 12px;
box-shadow: 0 2px 8px rgba(0,0,0,0.08);
border: 1px solid #e5e7eb;
```

## Spacing System

- **Form Group Margin:** 25px bottom
- **Card Padding:** 30px
- **Section Spacing:** 20-30px between sections
- **Label to Input:** 8px
- **Helper Text Margin:** 6px top

## Border Radius

- **Small:** 4px (small elements)
- **Medium:** 8px (inputs, buttons)
- **Large:** 12-14px (cards, containers)

## Box Shadows

```css
/* Light Shadow */
box-shadow: 0 2px 8px rgba(0,0,0,0.08);

/* Medium Shadow */
box-shadow: 0 4px 12px rgba(0,0,0,0.12);

/* Focus Shadow */
box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
```

## Icons

- Uses WordPress Dashicons
- Size: 18-20px for UI elements
- Color matches context (yellow for primary, muted for secondary)

## Responsive Breakpoints

```css
@media (max-width: 782px) {
  /* Stack form fields full width */
  /* Adjust tab navigation */
  /* Increase touch targets */
}
```

## Transitions

```css
transition: all 0.2s ease;
```
- Used on hover states
- Used on focus states
- Smooth, professional feel

## Code Block Styling

```css
display: block;
padding: 12px;
background: white;
border: 2px solid var(--ptp-border);
border-radius: 8px;
font-family: 'Courier New', monospace;
word-break: break-all;
```

## Best Practices Applied

1. **Consistent Spacing** - Uses multiples of 4px for all spacing
2. **Color Hierarchy** - Primary actions in yellow, destructive in red
3. **Typography Scale** - Clear hierarchy with consistent sizing
4. **Touch Targets** - Minimum 44px for mobile interactions
5. **Focus Indicators** - Clear yellow outline on all interactive elements
6. **Status Communication** - Color-coded alerts with icons
7. **Loading States** - Disabled states with reduced opacity
8. **Error Prevention** - Helper text and placeholders guide input
9. **Visual Feedback** - Hover and active states on all clickable elements
10. **Accessibility** - Proper contrast ratios, semantic HTML

This styling creates a professional, cohesive experience that matches the PTP brand while maintaining WordPress admin familiarity.
