-- Sirf `department` column add karta hai (VARCHAR(100) NULL, `c_action_1_remark` ke baad).
-- Dispatch tables is file mein NAHI hain — unme pehle se department hai.
-- Agar "Duplicate column" aaye to us table skip karo — column pehle se hai.

ALTER TABLE `label_issue_issues` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `label_issue_issue_histories` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `carrier_issue_issues` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `carrier_issue_issue_histories` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `other_issue_issues` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `other_issue_issue_histories` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `c_care_issue_issues` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `c_care_issue_issue_histories` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `listing_issue_issues` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `listing_issue_issue_histories` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `qc_and_packing_issues` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;
ALTER TABLE `qc_and_packing_issue_histories` ADD COLUMN `department` VARCHAR(100) NULL AFTER `c_action_1_remark`;

-- Laravel migration row register karna ho (sirf jab SQL se apply kiya ho aur artisan migrate pending dikhaaye):
-- Migration name: 2026_04_12_200000_add_department_to_customer_care_issue_tables
-- `migrations` table mein ek nayi row apne next batch number ke sath add karo.
