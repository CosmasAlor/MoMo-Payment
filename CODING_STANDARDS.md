# MTN MoMo Payment Gateway - Coding Standards

## 1. Think Before Coding

### Assumptions
- Always state assumptions explicitly
- If uncertain about route patterns, ask for clarification
- Multiple interpretations exist → present options, don't pick silently
- Simpler approach exists → suggest it, push back when warranted

### Clarity
- If something is unclear → stop and name what's confusing
- Ask for clarification before proceeding
- Don't hide confusion or proceed with assumptions

## 2. Simplicity First

### Minimum Code Principle
- Write only what solves the problem
- No speculative features beyond requirements
- No abstractions for single-use code
- No "flexibility" or "configurability" unless requested
- No error handling for impossible scenarios

### Code Review Test
- Before writing 200 lines → ask: "Can this be 50 lines?"
- If yes → rewrite it
- Ask: "Would a senior engineer say this is overcomplicated?"
- If yes → simplify

## 3. Surgical Changes

### Edit Existing Code
- Touch only what you must
- Don't "improve" adjacent code, comments, or formatting
- Don't refactor things that aren't broken
- Match existing style, even if you'd do it differently
- Notice unrelated dead code → mention it, don't delete

### Clean Up Own Mess
- Remove imports/variables/functions that YOUR changes made unused
- Don't remove pre-existing dead code unless asked
- Every changed line should trace directly to user request

## 4. Goal-Driven Execution

### Verifiable Success Criteria
- Transform tasks into verifiable goals
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix bug" → "Write test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

### Multi-Step Tasks
State brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria enable independent looping. Weak criteria ("make it work") require constant clarification.

## 5. MTN Gateway Specific Rules

### URL Pattern Requirements
- System uses: `?_route=` NOT `index.php?route=`
- Base URL: `http://2.58.80.82:8085/?_route=paymentgateway/`
- Valid actions: `mtn_config`, `mtn_pay`, `mtn_callback`
- Flexible route detection required (base route + action)

### Security Requirements
- Block direct file access: `if (basename($_SERVER["PHP_SELF"]) == basename(__FILE__) && !isset($_GET["_route"]))`
- Allow access with `?_route=` parameter
- Validate phone format: `9XXXXXXXX` (9 digits starting with 9)

### Database Requirements
- Auto-create tables on first run
- Fallback config for standalone testing
- PDO connection with error handling
- Analytics tracking for payments

### Function Requirements
- `mtn_admin_menu()` - Base route navigation
- `mtn_admin_page()` - Configuration settings
- `mtn_payment_page()` - Payment interface
- `mtn_callback()` - Status handler

### Testing Requirements
- Test numbers: 912345678 (success), 923456789 (failed), 934567890 (pending)
- Test PIN: 12345
- Debug information for troubleshooting
- Remove debug in production

## 6. Code Style Guidelines

### PHP Standards
- Use PSR-4 autoloading style
- Class names: PascalCase (`MoMoDatabase`)
- Function names: snake_case (`mtn_admin_page`)
- Variables: snake_case (`$momo_db`)
- Constants: UPPER_CASE (`IN_PHPNUXBILL`)

### Security
- Always validate user input
- Use prepared statements for database queries
- Escape output with `htmlspecialchars()`
- Never trust client-side validation

### Error Handling
- Log errors with `error_log()`
- Provide user-friendly error messages
- Graceful degradation for database failures
- Return meaningful status codes

### Performance
- Minimize database queries
- Use appropriate data types
- Cache configuration values
- Optimize for shared hosting environments

## 7. Development Workflow

### Before Each Change
1. Read existing code thoroughly
2. Identify exact requirement
3. Plan minimal change
4. Consider edge cases
5. Write test cases

### During Development
1. Implement smallest possible change
2. Test immediately
3. Fix issues before proceeding
4. Document non-obvious logic
5. Follow existing patterns

### After Each Change
1. Verify all routes still work
2. Test security checks
3. Check database operations
4. Validate user interface
5. Update documentation

## 8. Quality Checklist

### Code Review
- [ ] Code follows existing style
- [ ] No unused variables/functions
- [ ] All inputs validated
- [ ] Database queries use prepared statements
- [ ] Error handling implemented
- [ ] Security checks in place

### Functionality
- [ ] All URL patterns work
- [ ] Base route shows menu
- [ ] Payment flow complete
- [ ] Callback processes correctly
- [ ] Configuration saves/loads

### Testing
- [ ] Test numbers work
- [ ] Invalid inputs rejected
- [ ] Database errors handled
- [ ] Direct access blocked
- [ ] Debug information helpful

---

**Follow these standards for all future MTN gateway development to maintain code quality and consistency.**
