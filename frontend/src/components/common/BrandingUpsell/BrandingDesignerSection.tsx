import {useState} from "react";
import {Anchor, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconEyeOff} from "@tabler/icons-react";
import {NavLink, useLocation} from "react-router";
import {DesignerSection} from "../DesignerShell";
import {useBrandingSubscription} from "../../../hooks/useBrandingSubscription.ts";
import {BrandingUpgradeModal} from "./BrandingUpgradeModal.tsx";
import {getConfig} from "../../../utilites/config.ts";

export const BrandingDesignerSection = () => {
    const location = useLocation();
    const {isAvailable, isActive, canManageBilling} = useBrandingSubscription();
    const [modalOpen, setModalOpen] = useState(false);

    if (!isAvailable) {
        return null;
    }

    const appName = getConfig("VITE_APP_NAME", "NearU");

    const description = isActive ? (
        <>
            {t`Included in your plan. ${appName} branding is hidden on your event pages, widgets, tickets and emails.`}
            {canManageBilling && (
                <>
                    {' '}
                    <Anchor component={NavLink} to="/settings/billing" state={{returnTo: location.pathname}} size="xs">
                        {t`Manage billing`}
                    </Anchor>
                </>
            )}
        </>
    ) : t`Remove "Powered by ${appName}" from your pages, widgets, tickets and emails, and show your own logo instead.`;

    return (
        <DesignerSection icon={<IconEyeOff/>} title={t`Branding`}>
            <Switch
                label={t`Hide ${appName} branding`}
                description={description}
                checked={isActive}
                disabled={isActive}
                aria-haspopup={isActive ? undefined : 'dialog'}
                onChange={() => setModalOpen(true)}
            />
            <BrandingUpgradeModal opened={modalOpen} onClose={() => setModalOpen(false)}/>
        </DesignerSection>
    );
};
