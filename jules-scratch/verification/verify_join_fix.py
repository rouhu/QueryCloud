from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # 1. Login
        page.goto("http://localhost:8000/login.php")
        page.get_by_placeholder("Username").fill("admin")
        page.get_by_placeholder("Password").fill("adminpass")
        page.get_by_role("button", name="Login").click()
        expect(page).to_have_url("http://localhost:8000/dashboard.php")

        # 2. Click the first "Edit SQL/Visual" button
        edit_button = page.locator(".btn-edit-saved-query").first
        edit_button.click()

        # 3. Wait for VQB modal and add a new join
        modal = page.locator("#modal-visual-query")
        expect(modal).to_be_visible()

        page.locator("#btnJoinTable").click()

        # 4. Assert that the new join's table dropdown is populated
        new_join_row = page.locator(".cloned-join-row").last
        if not new_join_row.is_visible():
             new_join_row = page.locator("#fieldCloneTable")

        table_dropdown = new_join_row.locator("select.jointable")

        # We expect more than one option (the default "Choose Table" plus the actual tables)
        expect(table_dropdown.locator("option")).to_have_count(1, timeout=10000)

        # 5. Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")
    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
