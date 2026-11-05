import {t} from "@lingui/macro";
import {IconCheck} from "@tabler/icons-react";
import classes from "./BrandingUpsell.module.scss";
import {getConfig} from "../../../utilites/config.ts";

export const IncludedFeatures = () => {
    const appName = getConfig("VITE_APP_NAME", "NearU");

    return (
        <ul className={classes.features}>
            <li>
                <IconCheck size={16} stroke={2.25} aria-hidden/>
                {t`No ${appName} branding on your event pages, organizer pages, checkout or embedded widgets`}
            </li>
            <li>
                <IconCheck size={16} stroke={2.25} aria-hidden/>
                {t`Your organizer logo at the top of order and ticket emails, in place of the ${appName} logo`}
            </li>
            <li>
                <IconCheck size={16} stroke={2.25} aria-hidden/>
                {t`Emails sent under your organizer name, with buttons and links in your event's colour`}
            </li>
            <li>
                <IconCheck size={16} stroke={2.25} aria-hidden/>
                {t`No ${appName} promotion in the emails your attendees receive`}
            </li>
        </ul>
    );
};
