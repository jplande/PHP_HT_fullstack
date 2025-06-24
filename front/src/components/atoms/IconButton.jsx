import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";

const IconButton = ({ icon, onClick, className }) => {
    return (
        <button type="button" className={className} onClick={onClick}>
            <FontAwesomeIcon icon={icon} />
        </button>
    )
};
export default IconButton;
