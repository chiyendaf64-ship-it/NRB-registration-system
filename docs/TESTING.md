# Test checklist (take a screenshot of each; hide any password)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Register citizen | Register Applicant > fill valid data > Save | Success message, applicant page opens |
| 2 | Register foreigner | Category = Resident foreigner, passport UG9999999 | Saved with passport |
| 3 | Listing/search | Applicants > search "Banda", "MW-1998-0001", "NRB-2026-0001" | Matching rows with centre and card |
| 4 | Update | Edit applicant 4 > Status = Approved | Success; status badge changes |
| 5 | Issue card | ID Cards > choose approved registration > card NRB-2026-0004 | Card listed as Active |
| 6 | Change card status | Set a card to Lost | Status updated |
| 7 | Invalid input | Empty surname, future DOB, expiry before issue | Error list, nothing saved |
| 8 | Duplicate | Reuse Citizen ID MW-1998-0001 / card NRB-2026-0001 | "already registered/issued" message |
| 9 | Constraint | Delete applicant 1 (has card) | Blocked with message |
| 10 | Delete | Delete an applicant without a card | Applicant and linked rows removed |
| 11 | XSS | Enter `<script>alert(1)</script>` as village | Fails validation; never executes |
