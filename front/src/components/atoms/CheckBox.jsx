import Title from "./Title";

const CheckBox = ({ optionName, name,  value, id, checked, onChange }) => {
    return (
        <div className="mb-3">
            <div className="form-check">
                {/* className="form-check-input"
                type="radio"
                id={id}
                name={name}
                value={value}
                checked={checked}
                onChange={onChange} */}

                <input
                    className="form-check-input"
                    type="radio"
                    name={name}
                    id={id}
                    value={value}
                    checked={checked}
                    onChange={onChange}
                />
                <Title className="form-label" for={id}>
                    {optionName}
                </Title>
            </div>
        </div>
    );
};
export default CheckBox;
