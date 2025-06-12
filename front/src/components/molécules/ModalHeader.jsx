import { Button } from "../atoms";
const ModalHeader = ({ onHide, modalTitle }) => {
    return (
        <div className="modal-header">
            <h5 className="modal-title">{modalTitle}</h5>
            <Button
                type="Button"
                className="btn-close"
                onClick={onHide}
            ></Button>
        </div>
    );
};
export default ModalHeader;
